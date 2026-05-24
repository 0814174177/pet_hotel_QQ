<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomPet;
use App\Models\BookingServicePet;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Room;
use App\Models\Service;
use App\Models\TypeRoom;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingRepository implements BookingRepositoryInterface
{
    public function customerForUser(?User $user): ?Customer
    {
        if (! $user) {
            return null;
        }

        return $user->customer ?: Customer::where('user_id', $user->id)->first();
    }

    public function bookingFormViewData(string $branchId, bool $isAuthenticated, ?User $user = null): array
    {
        $branches = $this->bookingBranches();
        $selectedBranch = collect($branches)->firstWhere('id', $branchId) ?: $branches[0];

        return [
            'id' => $selectedBranch['id'],
            'branchId' => $selectedBranch['id'],
            'bookingData' => [
                'today' => now()->toDateString(),
                'isAuthenticated' => $isAuthenticated,
                'loginUrl' => route('authentication.login'),
                'branch' => $selectedBranch,
                'branches' => $branches,
                'roomTypes' => $this->bookingRoomTypes((string) $selectedBranch['id']),
                'pets' => $this->customerPets($this->customerForUser($user)),
                'services' => $this->bookingServices(),
                'availability' => $this->bookingAvailability((string) $selectedBranch['id']),
            ],
        ];
    }

    public function bookingBranches(): array
    {
        $branches = Branch::where('is_active', 1)
            ->orderBy('branch_name')
            ->get();

        if ($branches->isEmpty()) {
            return $this->fallbackBranches();
        }

        $branchImages = $this->randomBranchImages($branches->count());

        return $branches
            ->values()
            ->map(fn (Branch $branch, int $index): array => [
                'id' => (string) $branch->branch_id,
                'name' => $branch->branch_name,
                'address' => $branch->address,
                'phone' => $branch->phone ?: 'Đang cập nhật',
                'hours' => '8:00 - 20:00',
                'rating' => '4.8',
                'reviews' => 0,
                'image' => $branchImages[$index] ?? asset('assets/client/images/right-home-500x554.png'),
                'bookingUrl' => url('/booking/branch/'.$branch->branch_id),
            ])
            ->all();
    }

    private function fallbackBranches(): array
    {
        $branchImages = $this->randomBranchImages(4);

        return [
            [
                'id' => '1',
                'name' => 'Pet Hotel Quận 7',
                'address' => '123 Đường Nguyễn Văn Linh, Quận 7, TP.HCM',
                'phone' => '1900 1234',
                'hours' => '8:00 - 20:00',
                'rating' => '4.8',
                'reviews' => 127,
                'image' => $branchImages[0],
                'bookingUrl' => url('/booking/branch/1'),
            ],
            [
                'id' => '2',
                'name' => 'Pet Hotel Quận 1',
                'address' => '45 Đường Lê Lợi, Quận 1, TP.HCM',
                'phone' => '1900 5678',
                'hours' => '7:30 - 21:00',
                'rating' => '4.6',
                'reviews' => 89,
                'image' => $branchImages[1],
                'bookingUrl' => url('/booking/branch/2'),
            ],
            [
                'id' => '3',
                'name' => 'Pet Hotel Bình Thạnh',
                'address' => '321 Đường Xô Viết Nghệ Tĩnh, Bình Thạnh, TP.HCM',
                'phone' => '1900 3456',
                'hours' => '8:00 - 20:00',
                'rating' => '4.7',
                'reviews' => 203,
                'image' => $branchImages[2],
                'bookingUrl' => url('/booking/branch/3'),
            ],
            [
                'id' => '4',
                'name' => 'Pet Hotel Thủ Đức',
                'address' => '789 Đường Võ Văn Ngân, TP. Thủ Đức, TP.HCM',
                'phone' => '1900 9012',
                'hours' => '8:00 - 19:00',
                'rating' => '4.5',
                'reviews' => 61,
                'image' => $branchImages[3],
                'bookingUrl' => url('/booking/branch/4'),
            ],
        ];
    }

    public function bookingHistoryItems(Customer $customer): array
    {
        return Booking::with($this->bookingRelations())
            ->where('customer_id', $customer->customer_id)
            ->orderByDesc('checkin_expected_at')
            ->get()
            ->map(fn (Booking $booking): array => $this->bookingSummary($booking))
            ->values()
            ->all();
    }

    public function findCustomerBooking(Customer $customer, string $bookingId): ?Booking
    {
        return Booking::with($this->bookingRelations())
            ->where('customer_id', $customer->customer_id)
            ->where('booking_id', $bookingId)
            ->first();
    }

    public function bookingDetail(Booking $booking): array
    {
        $summary = $this->bookingSummary($booking);
        $status = $this->bookingDisplayStatus($booking);

        return [
            ...$summary,
            'status' => $status,
            'checkin' => $this->formatDateTime($booking->checkin_expected_at),
            'checkout' => $this->formatDateTime($booking->checkout_expected_at),
            'nights' => $this->bookingNights($booking),
            'branch' => [
                'name' => $booking->branch?->branch_name ?: 'Chi nhánh đang cập nhật',
                'address' => $booking->branch?->address ?: 'Đang cập nhật',
                'phone' => $booking->branch?->phone ?: 'Đang cập nhật',
            ],
            'rooms' => $this->bookingRoomsFor($booking),
            'pets' => $this->bookingPetsFor($booking),
            'services' => $this->bookingServicesFor($booking),
            'total_amount' => $this->bookingTotalAmount($booking),
            'note' => $booking->special_notes ?? $booking->special_note ?? null,
        ];
    }

    public function roomTypeAvailable(string|int $branchId, string|int $typeRoomId): bool
    {
        return Room::where('branch_id', $branchId)
            ->where('type_room_id', $typeRoomId)
            ->where('status', 'AVAILABLE')
            ->exists();
    }

    public function dateRangeAvailable(
        string|int $branchId,
        string|int $typeRoomId,
        string $checkin,
        string $checkout
    ): bool {
        $checkinDate = Carbon::parse($checkin)->startOfDay();
        $checkoutDate = Carbon::parse($checkout)->startOfDay();
        $unavailableDates = collect($this->fullyBookedDatesForType($branchId, $typeRoomId))
            ->mapWithKeys(fn (string $date): array => [$date => true]);
        $today = Carbon::today();

        if ($checkinDate->lt($today) || $checkoutDate->lte($checkinDate)) {
            return false;
        }

        for ($date = $checkinDate->copy(); $date->lt($checkoutDate); $date->addDay()) {
            if ($date->lt($today) || $unavailableDates->has($date->toDateString())) {
                return false;
            }
        }

        return true;
    }

    public function petsCanBeBookedBy(?User $user, array $petIds): bool
    {
        if (! $user || $petIds === []) {
            return false;
        }

        $uniquePetIds = collect($petIds)->map(fn ($petId): int => (int) $petId)->unique()->values();
        $pets = Pet::whereIn('pet_id', $uniquePetIds)->get();

        if ($pets->count() !== $uniquePetIds->count()) {
            return false;
        }

        return $pets->every(fn (Pet $pet): bool => Gate::forUser($user)->allows('book', $pet));
    }

    private function petBookingConflictMessage(array $petIds, string $checkin, string $checkout): ?string
    {
        $uniquePetIds = collect($petIds)
            ->map(fn ($petId): int => (int) $petId)
            ->unique()
            ->values();

        if ($uniquePetIds->isEmpty()) {
            return null;
        }

        $currentRoomConflicts = DB::table('booking_room_pet')
            ->join('booking_room', 'booking_room_pet.booking_room_id', '=', 'booking_room.booking_room_id')
            ->join('booking', 'booking_room.booking_id', '=', 'booking.booking_id')
            ->join('pet', 'booking_room_pet.pet_id', '=', 'pet.pet_id')
            ->whereIn('booking_room_pet.pet_id', $uniquePetIds->all())
            ->where('booking.status', 'CHECKED_IN')
            ->whereNull('booking.checkout_actual_at')
            ->select(['pet.pet_id', 'pet.pet_name'])
            ->orderBy('pet.pet_name')
            ->get()
            ->unique('pet_id')
            ->values();

        if ($currentRoomConflicts->isNotEmpty()) {
            $petNames = $currentRoomConflicts
                ->map(fn ($conflict): string => $conflict->pet_name ?: 'Thú cưng #'.$conflict->pet_id)
                ->implode(', ');

            return sprintf(
                '%s đang ở trong phòng khác. Vui lòng checkout thú cưng trước khi thêm vào booking mới.',
                $petNames
            );
        }

        $checkinAt = Carbon::parse($checkin);
        $checkoutAt = Carbon::parse($checkout);

        $conflicts = DB::table('booking_room_pet')
            ->join('booking_room', 'booking_room_pet.booking_room_id', '=', 'booking_room.booking_room_id')
            ->join('booking', 'booking_room.booking_id', '=', 'booking.booking_id')
            ->join('pet', 'booking_room_pet.pet_id', '=', 'pet.pet_id')
            ->whereIn('booking_room_pet.pet_id', $uniquePetIds->all())
            ->whereIn('booking.status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])
            ->where('booking.checkin_expected_at', '<', $checkoutAt->toDateTimeString())
            ->where('booking.checkout_expected_at', '>', $checkinAt->toDateTimeString())
            ->select([
                'booking.booking_id',
                'booking.checkin_expected_at',
                'booking.checkout_expected_at',
                'pet.pet_id',
                'pet.pet_name',
            ])
            ->orderBy('booking.checkin_expected_at')
            ->get()
            ->unique('pet_id')
            ->values();

        if ($conflicts->isEmpty()) {
            return null;
        }

        $petNames = $conflicts
            ->map(fn ($conflict): string => $conflict->pet_name ?: 'Thú cưng #'.$conflict->pet_id)
            ->implode(', ');
        $firstConflict = $conflicts->first();
        $conflictRange = $this->formatConflictDateRange(
            $firstConflict->checkin_expected_at,
            $firstConflict->checkout_expected_at
        );

        return sprintf(
            '%s đang có booking khác trong khoảng %s. Vui lòng chọn thú cưng hoặc ngày lưu trú khác.',
            $petNames,
            $conflictRange
        );
    }

    public function createPendingBookingForUser(?User $user, array $bookingData): Booking
    {
        if (! $user) {
            throw new Exception('email|Vui lòng đăng nhập trước khi thanh toán.');
        }

        $customer = $this->customerForUser($user);

        if (! $customer) {
            throw new Exception('email|Vui lòng cập nhật thông tin khách hàng trước khi thanh toán.');
        }

        if (! $this->roomTypeAvailable($bookingData['branch_id'], $bookingData['room_type'])) {
            throw new Exception('room_type|Loại phòng này hiện không còn phòng trống tại chi nhánh đã chọn.');
        }

        if (! $this->petsCanBeBookedBy($user, $bookingData['pet_ids'])) {
            throw new Exception('pet_ids|Bạn chỉ có thể đặt phòng cho thú cưng thuộc tài khoản của mình.');
        }

        if ($message = $this->petBookingConflictMessage(
            $bookingData['pet_ids'],
            $bookingData['checkin_expected_at'],
            $bookingData['checkout_expected_at']
        )) {
            throw new Exception('pet_ids|'.$message);
        }

        if (! $this->dateRangeAvailable(
            $bookingData['branch_id'],
            $bookingData['room_type'],
            $bookingData['checkin_expected_at'],
            $bookingData['checkout_expected_at']
        )) {
            throw new Exception('checkin_expected_at|Khoảng thời gian này không còn phòng trống. Vui lòng chọn ngày khác.');
        }

        return $this->assignRoomAndServicesWithLock([
            ...$bookingData,
            'customer_id' => $customer->customer_id,
            'employee_id' => null,
        ]);
    }

    public function assignRoomAndServicesWithLock(array $data): Booking
    {
        return DB::transaction(function () use ($data): Booking {
            try {
                $pets = Pet::whereIn('pet_id', $data['pet_ids'] ?? [$data['pet_id']])
                    ->lockForUpdate()
                    ->get();

                if ($message = $this->petBookingConflictMessage(
                    $data['pet_ids'] ?? [$data['pet_id']],
                    $data['checkin_expected_at'],
                    $data['checkout_expected_at']
                )) {
                    throw new Exception('pet_ids|'.$message);
                }

                $room = Room::where('branch_id', $data['branch_id'])
                    ->where('type_room_id', $data['room_type'])
                    ->where('status', 'AVAILABLE')
                    ->whereDoesntHave('bookingRooms.booking', function ($query) use ($data): void {
                        $query->whereIn('status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])
                            ->where('checkin_expected_at', '<', $data['checkout_expected_at'])
                            ->where('checkout_expected_at', '>', $data['checkin_expected_at']);
                    })
                    ->inRandomOrder()
                    ->lockForUpdate()
                    ->first();

                if (! $room) {
                    throw new Exception('NO_ROOM|Không tìm thấy phòng trống phù hợp tại chi nhánh đã chọn.');
                }

                $this->assertPetsFitRoomType($pets, $room);

                $booking = Booking::create([
                    'customer_id' => $data['customer_id'],
                    'branch_id' => $data['branch_id'],
                    'checkin_expected_at' => $data['checkin_expected_at'],
                    'checkout_expected_at' => $data['checkout_expected_at'],
                    'status' => 'PENDING',
                ]);

                $bookingRoom = BookingRoom::create([
                    'booking_id' => $booking->booking_id,
                    'room_id' => $room->room_id,
                    'assigned_at' => now(),
                ]);

                foreach ($data['pet_ids'] ?? [$data['pet_id']] as $petId) {
                    BookingRoomPet::create([
                        'booking_room_id' => $bookingRoom->booking_room_id,
                        'pet_id' => $petId,
                    ]);
                }

                $serviceTotal = 0.0;
                $servicePetIds = $data['service_pet_ids'] ?? [];
                $serviceIds = collect($servicePetIds)
                    ->flatten()
                    ->unique()
                    ->values()
                    ->all();

                $services = Service::whereIn('service_id', $serviceIds)
                    ->get()
                    ->keyBy('service_id');

                foreach ($servicePetIds as $petId => $petServiceIds) {
                    foreach ($petServiceIds as $serviceId) {
                        $service = $services->get($serviceId);
                        $serviceTotal += (float) ($service?->base_price ?? 0);

                        BookingServicePet::create([
                            'booking_id' => $booking->booking_id,
                            'pet_id' => $petId,
                            'service_id' => $serviceId,
                            'employee_id' => $data['employee_id'] ?? null,
                            'scheduled_at' => $data['checkin_expected_at'],
                            'status' => 'PENDING',
                        ]);
                    }
                }

                $booking->update([
                    'total_amount' => $this->estimatedBookingTotal($room, $booking, $serviceTotal),
                ]);

                return $booking->load($this->bookingRelations());
            } catch (QueryException $e) {
                Log::error('Database Error in Booking: '.$e->getMessage(), [
                    'branch_id' => $data['branch_id'] ?? null,
                    'room_type' => $data['room_type'] ?? null,
                    'customer_id' => $data['customer_id'] ?? null,
                ]);

                throw new Exception('DB_ERROR|Đã xảy ra lỗi hệ thống khi lưu thông tin. Vui lòng thử lại.');
            }
        });
    }

    private function assertPetsFitRoomType($pets, Room $room): void
    {
        $typeRoom = $room->typeRoom;

        if (! $typeRoom) {
            throw new Exception('room_type|Loại phòng đang không khả dụng. Vui lòng chọn phòng khác.');
        }

        $maxSlot = max(1, (int) $typeRoom->max_slot);

        if ($pets->count() > $maxSlot) {
            throw new Exception(sprintf(
                'pet_ids|Phòng %s chỉ nhận tối đa %d thú cưng. Vui lòng bớt số lượng thú cưng hoặc chọn loại phòng khác.',
                $typeRoom->type_name ?: 'đã chọn',
                $maxSlot
            ));
        }

        $minWeight = $typeRoom->pet_weight_min_kg !== null ? (float) $typeRoom->pet_weight_min_kg : null;
        $maxWeight = $typeRoom->pet_weight_max_kg !== null ? (float) $typeRoom->pet_weight_max_kg : null;

        if ($minWeight === null && $maxWeight === null) {
            return;
        }

        $invalidPets = $pets
            ->filter(function (Pet $pet) use ($minWeight, $maxWeight): bool {
                if ($pet->weight_kg === null) {
                    return false;
                }

                $weight = (float) $pet->weight_kg;

                return ($minWeight !== null && $weight < $minWeight)
                    || ($maxWeight !== null && $weight > $maxWeight);
            })
            ->map(fn (Pet $pet): string => sprintf('%s (%skg)', $pet->pet_name ?: 'Thú cưng #'.$pet->pet_id, (float) $pet->weight_kg))
            ->values();

        if ($invalidPets->isEmpty()) {
            return;
        }

        $weightRange = match (true) {
            $minWeight !== null && $maxWeight !== null => sprintf('từ %skg đến %skg', $minWeight, $maxWeight),
            $minWeight !== null => sprintf('từ %skg trở lên', $minWeight),
            default => sprintf('không quá %skg', $maxWeight),
        };

        throw new Exception(sprintf(
            'pet_ids|%s không phù hợp với phòng %s. Phòng này chỉ nhận thú cưng %s.',
            $invalidPets->implode(', '),
            $typeRoom->type_name ?: 'đã chọn',
            $weightRange
        ));
    }

    private function bookingRelations(): array
    {
        return [
            'branch',
            'bookingRooms.room.typeRoom',
            'bookingRooms.bookingRoomPets.pet',
            'bookingServicePets.service',
            'bookingServicePets.pet',
            'orders',
        ];
    }

    private function bookingSummary(Booking $booking): array
    {
        $status = strtoupper((string) $booking->status);
        $isPaid = $this->bookingHasCompletedOrder($booking);
        $statusMeta = $this->bookingStatusMeta($isPaid ? 'PAID' : $status);
        $petNames = $this->bookingPetNames($booking);
        $roomTypes = $this->bookingRoomTypesFor($booking);
        $roomLabel = $roomTypes ? implode(', ', $roomTypes) : 'Chưa phân phòng';

        return [
            'id' => (string) $booking->booking_id,
            'title' => sprintf(
                '%s - %s',
                $petNames ? implode(', ', $petNames) : 'Booking '.$booking->booking_id,
                $roomLabel
            ),
            'pet_count' => count($petNames),
            'date_range' => $this->formatDateRange($booking),
            'branch_name' => $booking->branch?->branch_name ?: 'Chi nhánh đang cập nhật',
            'status_label' => $statusMeta['label'],
            'status_class' => $statusMeta['class'],
            'icon_class' => $statusMeta['icon'],
            'group' => $this->bookingHistoryGroup($booking),
            'detail_url' => route('booking.show', $booking->booking_id),
            'payment_url' => url('/payment?booking_id='.$booking->booking_id),
            'show_payment' => ! $isPaid && in_array($status, ['PENDING', 'CONFIRMED'], true),
        ];
    }

    private function bookingHistoryGroup(Booking $booking): string
    {
        $status = strtoupper((string) $booking->status);

        if ($status === 'CANCELLED') {
            return 'cancelled';
        }

        if (in_array($status, ['CHECKED_OUT', 'COMPLETED'], true)) {
            return 'done';
        }

        return 'active';
    }

    private function bookingStatusMeta(string $status): array
    {
        return match ($status) {
            'PAID' => ['label' => 'Đã thanh toán', 'class' => 'status-paid', 'icon' => 'blue'],
            'CANCELLED' => ['label' => 'Đã hủy', 'class' => 'status-cancelled', 'icon' => 'red'],
            'CHECKED_OUT', 'COMPLETED' => ['label' => 'Đã thanh toán', 'class' => 'status-paid', 'icon' => 'blue'],
            'CHECKED_IN' => ['label' => 'Đang lưu trú', 'class' => 'status-pending', 'icon' => 'blue'],
            'CONFIRMED' => ['label' => 'Đã xác nhận', 'class' => 'status-pending', 'icon' => ''],
            default => ['label' => 'Đã giữ chỗ', 'class' => 'status-pending', 'icon' => ''],
        };
    }

    private function bookingDisplayStatus(Booking $booking): string
    {
        return $this->bookingHasCompletedOrder($booking)
            ? 'PAID'
            : strtoupper((string) $booking->status);
    }

    private function bookingHasCompletedOrder(Booking $booking): bool
    {
        return $booking->orders->contains(
            fn ($order): bool => in_array(strtoupper((string) $order->status), ['COMPLETED', 'PAID'], true)
        );
    }

    private function bookingPetNames(Booking $booking): array
    {
        $roomPets = $booking->bookingRooms
            ->flatMap(fn ($bookingRoom) => $bookingRoom->bookingRoomPets)
            ->map(fn ($bookingRoomPet) => $bookingRoomPet->pet?->pet_name)
            ->filter();

        $servicePets = $booking->bookingServicePets
            ->map(fn ($bookingServicePet) => $bookingServicePet->pet?->pet_name)
            ->filter();

        return $roomPets->merge($servicePets)->unique()->values()->all();
    }

    private function bookingRoomTypesFor(Booking $booking): array
    {
        return $booking->bookingRooms
            ->map(fn ($bookingRoom) => $bookingRoom->room?->typeRoom?->type_name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function bookingRoomsFor(Booking $booking): array
    {
        return $booking->bookingRooms
            ->map(fn ($bookingRoom) => [
                'room_number' => $bookingRoom->room?->room_number ?: 'Chưa phân phòng',
                'type' => $bookingRoom->room?->typeRoom?->type_name ?: 'Chưa phân loại',
                'price' => (float) ($bookingRoom->room?->typeRoom?->base_price_per_day ?: 0),
                'assigned_at' => $this->formatDateTime($bookingRoom->assigned_at),
            ])
            ->values()
            ->all();
    }

    private function bookingPetsFor(Booking $booking): array
    {
        return $booking->bookingRooms
            ->flatMap(fn ($bookingRoom) => $bookingRoom->bookingRoomPets)
            ->map(fn ($bookingRoomPet) => $bookingRoomPet->pet)
            ->filter()
            ->unique('pet_id')
            ->map(fn (Pet $pet) => [
                'name' => $pet->pet_name,
                'species' => $this->displaySpecies($pet->species),
                'breed' => $pet->breed ?: 'Chưa cập nhật',
                'weight' => filled($pet->weight_kg) ? (float) $pet->weight_kg : null,
            ])
            ->values()
            ->all();
    }

    private function bookingServicesFor(Booking $booking): array
    {
        return $booking->bookingServicePets
            ->map(fn ($bookingServicePet) => [
                'name' => $bookingServicePet->service?->service_name ?: 'Dịch vụ đang cập nhật',
                'pet_name' => $bookingServicePet->pet?->pet_name ?: 'Thú cưng',
                'price' => (float) ($bookingServicePet->service?->base_price ?: 0),
                'status' => $bookingServicePet->status ?: 'PENDING',
            ])
            ->values()
            ->all();
    }

    private function estimatedBookingTotal(Room $room, Booking $booking, float $serviceTotal = 0): float
    {
        $roomPrice = (float) ($room->typeRoom?->base_price_per_day ?? 0);

        return ($roomPrice * max(1, $this->bookingNights($booking))) + $serviceTotal;
    }

    private function bookingTotalAmount(Booking $booking): float
    {
        if (filled($booking->total_amount)) {
            return (float) $booking->total_amount;
        }

        $orderTotal = $booking->orders->sum(fn ($order) => (float) ($order->grand_total ?: 0));

        if ($orderTotal > 0) {
            return $orderTotal;
        }

        $nights = max(1, $this->bookingNights($booking));
        $roomTotal = $booking->bookingRooms->sum(
            fn (BookingRoom $bookingRoom): float => (float) ($bookingRoom->room?->typeRoom?->base_price_per_day ?: 0) * $nights
        );
        $serviceTotal = $booking->bookingServicePets->sum(
            fn (BookingServicePet $bookingServicePet): float => (float) ($bookingServicePet->service?->base_price ?: 0)
        );

        return $roomTotal + $serviceTotal;
    }

    private function bookingNights(Booking $booking): int
    {
        try {
            return (int) Carbon::parse($booking->checkin_expected_at)
                ->startOfDay()
                ->diffInDays(Carbon::parse($booking->checkout_expected_at)->startOfDay());
        } catch (Throwable) {
            return 0;
        }
    }

    private function bookingRoomTypes(string $branchId): array
    {
        return TypeRoom::where('is_active', 1)
            ->orderBy('base_price_per_day')
            ->get()
            ->map(function (TypeRoom $typeRoom) use ($branchId): array {
                $availableRoomsCount = Room::where('branch_id', $branchId)
                    ->where('type_room_id', $typeRoom->type_room_id)
                    ->where('status', 'AVAILABLE')
                    ->count();

                $maxPets = max(1, (int) $typeRoom->max_slot);
                $maxWeight = $typeRoom->pet_weight_max_kg !== null
                    ? (float) $typeRoom->pet_weight_max_kg
                    : 0.0;

                return [
                    'id' => (string) $typeRoom->type_room_id,
                    'name' => $typeRoom->type_name,
                    'price' => (float) $typeRoom->base_price_per_day,
                    'iconClass' => $this->roomIconClass($typeRoom),
                    'maxPets' => $maxPets,
                    'maxWeight' => $maxWeight,
                    'availableRoomsCount' => $availableRoomsCount,
                    'is_sold_out' => $availableRoomsCount === 0,
                    'detail' => $typeRoom->notes ?: sprintf(
                        'Phòng %s với sức chứa tối đa %d thú cưng.',
                        $typeRoom->type_name,
                        $maxPets
                    ),
                ];
            })
            ->values()
            ->all();
    }

    private function bookingServices(): array
    {
        try {
            return Service::where('is_active', 1)
                ->orderBy('service_name')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => (string) $service->service_id,
                    'name' => $service->service_name,
                    'price' => (int) $service->base_price,
                    'species' => $service->species ?: 'ALL',
                ])
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function bookingAvailability(string $branchId): array
    {
        return TypeRoom::where('is_active', 1)
            ->get()
            ->mapWithKeys(fn (TypeRoom $typeRoom): array => [
                (string) $typeRoom->type_room_id => [
                    'unavailable' => $this->fullyBookedDatesForType($branchId, $typeRoom->type_room_id),
                ],
            ])
            ->all();
    }

    private function fullyBookedDatesForType(string|int $branchId, string|int $typeRoomId): array
    {
        $totalPhysicalRooms = Room::where('branch_id', $branchId)
            ->where('type_room_id', $typeRoomId)
            ->count();

        if ($totalPhysicalRooms === 0) {
            return [];
        }

        $bookedRoomsByDate = [];
        $today = Carbon::today();

        $activeBookingRooms = DB::table('booking_room')
            ->join('booking', 'booking_room.booking_id', '=', 'booking.booking_id')
            ->join('room', 'booking_room.room_id', '=', 'room.room_id')
            ->where('room.branch_id', $branchId)
            ->where('room.type_room_id', $typeRoomId)
            ->whereIn('booking.status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])
            ->whereDate('booking.checkout_expected_at', '>=', $today->toDateString())
            ->select(['booking_room.room_id', 'booking.checkin_expected_at', 'booking.checkout_expected_at'])
            ->get();

        foreach ($activeBookingRooms as $bookingRoom) {
            try {
                $start = Carbon::parse($bookingRoom->checkin_expected_at)->startOfDay();
                $end = Carbon::parse($bookingRoom->checkout_expected_at)->startOfDay();
            } catch (Throwable) {
                continue;
            }

            if ($start->lt($today)) {
                $start = $today->copy();
            }

            for ($date = $start->copy(); $date->lt($end); $date->addDay()) {
                $bookedRoomsByDate[$date->toDateString()][(string) $bookingRoom->room_id] = true;
            }
        }

        return collect($bookedRoomsByDate)
            ->filter(fn (array $roomIds): bool => count($roomIds) >= $totalPhysicalRooms)
            ->keys()
            ->sort()
            ->values()
            ->all();
    }

    private function customerPets(?Customer $customer): array
    {
        if (! $customer) {
            return [];
        }

        try {
            return Pet::where('customer_id', $customer->customer_id)
                ->orderBy('pet_name')
                ->get()
                ->map(fn (Pet $pet) => [
                    'id' => (string) $pet->pet_id,
                    'name' => $pet->pet_name,
                    'species' => $this->displaySpecies($pet->species),
                    'breed' => $pet->breed ?: 'Chưa cập nhật',
                    'sex' => $this->displaySex($pet->sex),
                    'weight' => (float) ($pet->weight_kg ?: 0),
                    'is_in_room' => $this->petIsCurrentlyInRoom($pet),
                    'room_status_message' => 'Thú cưng này đang ở trong phòng khác.',
                    'note' => $pet->special_notes ?: 'Không có ghi chú đặc biệt',
                ])
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function petIsCurrentlyInRoom(Pet $pet): bool
    {
        return DB::table('booking_room_pet')
            ->join('booking_room', 'booking_room_pet.booking_room_id', '=', 'booking_room.booking_room_id')
            ->join('booking', 'booking_room.booking_id', '=', 'booking.booking_id')
            ->where('booking_room_pet.pet_id', $pet->pet_id)
            ->where('booking.status', 'CHECKED_IN')
            ->whereNull('booking.checkout_actual_at')
            ->exists();
    }

    private function randomBranchImages(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $images = $this->branchImageUrls();

        if ($images === []) {
            return array_fill(0, $count, asset('assets/client/images/right-home-500x554.png'));
        }

        shuffle($images);

        return collect(range(0, max(0, $count - 1)))
            ->map(fn (int $index): string => $images[$index % count($images)])
            ->all();
    }

    private function branchImageUrls(): array
    {
        static $urls = null;

        if ($urls !== null) {
            return $urls;
        }

        $directory = public_path('assets/client/images/branches');

        if (! is_dir($directory)) {
            return $urls = [];
        }

        $files = [];

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $files = [
                ...$files,
                ...(glob($directory.DIRECTORY_SEPARATOR.'*.'.$extension) ?: []),
                ...(glob($directory.DIRECTORY_SEPARATOR.'*.'.strtoupper($extension)) ?: []),
            ];
        }

        $files = array_values(array_unique($files));
        usort($files, 'strnatcasecmp');

        return $urls = collect($files)
            ->take(10)
            ->map(fn (string $path): string => asset('assets/client/images/branches/'.rawurlencode(basename($path))))
            ->values()
            ->all();
    }

    private function roomIconClass(TypeRoom $typeRoom): string
    {
        return match ((int) $typeRoom->type_room_id % 3) {
            2 => 'yellow',
            0 => 'purple',
            default => 'gray',
        };
    }

    private function formatDateRange(Booking $booking): string
    {
        try {
            return Carbon::parse($booking->checkin_expected_at)->format('d/m/Y')
                .' - '
                .Carbon::parse($booking->checkout_expected_at)->format('d/m/Y');
        } catch (Throwable) {
            return 'Đang cập nhật';
        }
    }

    private function formatConflictDateRange(mixed $checkin, mixed $checkout): string
    {
        try {
            return Carbon::parse($checkin)->format('d/m/Y H:i')
                .' - '
                .Carbon::parse($checkout)->format('d/m/Y H:i');
        } catch (Throwable) {
            return 'thoi gian da dat';
        }
    }

    private function formatDateTime(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (Throwable) {
            return 'Đang cập nhật';
        }
    }

    private function displaySpecies(?string $species): string
    {
        return match (strtoupper((string) $species)) {
            'DOG' => 'Chó',
            'CAT' => 'Mèo',
            'BIRD' => 'Chim',
            'RABBIT' => 'Thỏ',
            default => 'Khác',
        };
    }

    private function displaySex(?string $sex): string
    {
        return match (strtoupper((string) $sex)) {
            'MALE' => 'Đực',
            'FEMALE' => 'Cái',
            default => 'Chưa rõ',
        };
    }
}