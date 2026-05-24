<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomPet;
use App\Models\BookingServicePet;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Room;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function formData(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'data' => [
                'customer' => $customer->load('user'),
                'pets' => $customer->pets()->orderBy('pet_name')->get(),
                'branches' => Branch::where('is_active', 1)->orderBy('branch_name')->get(),
                'services' => Service::with('category')->where('is_active', 1)->orderBy('service_name')->get(),
                'rooms' => Room::with(['branch', 'typeRoom'])->where('status', 'AVAILABLE')->get(),
            ],
        ]);
    }

    public function formDataFromBranch(Request $request, string $branchId): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $branch = Branch::where('is_active', 1)->where('branch_id', $branchId)->firstOrFail();

        return response()->json([
            'data' => [
                'customer' => $customer->load('user'),
                'selected_branch' => $branch,
                'pets' => $customer->pets()->orderBy('pet_name')->get(),
                'services' => Service::with('category')->where('is_active', 1)->orderBy('service_name')->get(),
                'rooms' => Room::with('typeRoom')
                    ->where('branch_id', $branchId)
                    ->where('status', 'AVAILABLE')
                    ->get(),
            ],
        ]);
    }

    public function history(Request $request, string $bookingId): JsonResponse
    {
        return $this->show($request, $bookingId);
    }

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'data' => $customer->bookings()
                ->with(['branch', 'rooms', 'bookingServicesPet.service', 'bookingServicesPet.pet'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branch', 'branch_id')],
            'pet_ids' => ['required', 'array', 'min:1'],
            'pet_ids.*' => ['required', 'string', 'distinct', Rule::exists('pet', 'pet_id')],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'string', 'distinct', Rule::exists('services', 'service_id')],
            'room_id' => ['nullable', Rule::exists('room', 'room_id')],
            'checkin_expected_at' => ['required', 'date'],
            'checkout_expected_at' => ['required', 'date', 'after:checkin_expected_at'],
            'special_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensurePetsBelongToCustomer($validated['pet_ids'], $customer);
        $this->ensureRoomBelongsToBranch($validated['room_id'] ?? null, $validated['branch_id']);
        $this->ensurePetsAreAvailableForBooking(
            $validated['pet_ids'],
            $validated['checkin_expected_at'],
            $validated['checkout_expected_at']
        );

        $booking = DB::transaction(function () use ($customer, $validated) {
            Pet::whereIn('pet_id', $validated['pet_ids'])
                ->lockForUpdate()
                ->get();

            $this->ensurePetsAreAvailableForBooking(
                $validated['pet_ids'],
                $validated['checkin_expected_at'],
                $validated['checkout_expected_at']
            );

            $booking = Booking::create([
                'customer_id' => $customer->customer_id,
                'branch_id' => $validated['branch_id'],
                'checkin_expected_at' => $validated['checkin_expected_at'],
                'checkout_expected_at' => $validated['checkout_expected_at'],
                'status' => 'PENDING',
                'special_notes' => $validated['special_note'] ?? null,
            ]);

            if (! empty($validated['room_id'])) {
                $bookingRoom = BookingRoom::create([
                    'booking_id' => $booking->booking_id,
                    'room_id' => $validated['room_id'],
                    'assigned_at' => now(),
                    'notes' => $validated['special_note'] ?? null,
                ]);

                foreach ($validated['pet_ids'] as $petId) {
                    BookingRoomPet::create([
                        'booking_room_id' => $bookingRoom->booking_room_id,
                        'pet_id' => $petId,
                        'notes' => $validated['special_note'] ?? null,
                    ]);
                }
            }

            foreach ($validated['pet_ids'] as $petId) {
                foreach ($validated['service_ids'] ?? [] as $serviceId) {
                    BookingServicePet::create([
                        'booking_id' => $booking->booking_id,
                        'service_id' => $serviceId,
                        'employee_id' => null,
                        'pet_id' => $petId,
                        'scheduled_at' => $validated['checkin_expected_at'],
                        'status' => 'PENDING',
                        'notes' => $validated['special_note'] ?? null,
                    ]);
                }
            }

            return $booking->fresh(['branch', 'rooms', 'bookingServicesPet.service', 'bookingServicesPet.pet']);
        });

        return response()->json([
            'message' => 'Dat lich thanh cong. Booking dang cho xac nhan.',
            'data' => $booking,
        ], 201);
    }

    public function show(Request $request, string $bookingId): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $booking = $customer->bookings()
            ->with(['branch', 'rooms', 'bookingServicesPet.service', 'bookingServicesPet.pet'])
            ->where('booking_id', $bookingId)
            ->firstOrFail();

        return response()->json([
            'data' => $booking,
        ]);
    }

    public function cancel(Request $request, string $bookingId): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $booking = $customer->bookings()
            ->where('booking_id', $bookingId)
            ->firstOrFail();

        if (in_array($booking->status, ['CHECKED_IN', 'CHECKED_OUT', 'CANCELLED'], true)) {
            return response()->json([
                'message' => 'Booking nay khong the huy.',
            ], 422);
        }

        $booking->update([
            'status' => 'CANCELLED',
        ]);

        return response()->json([
            'message' => 'Huy booking thanh cong.',
            'data' => $booking->fresh(['branch', 'rooms', 'bookingServicesPet.service', 'bookingServicesPet.pet']),
        ]);
    }

    private function currentCustomer(Request $request): Customer
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Ban chua dang nhap.');

        return Customer::where('user_id', $user->id)->firstOrFail();
    }

    private function ensurePetsBelongToCustomer(array $petIds, Customer $customer): void
    {
        $ownedPetCount = Pet::where('customer_id', $customer->customer_id)
            ->whereIn('pet_id', $petIds)
            ->count();

        abort_if($ownedPetCount !== count(array_unique($petIds)), 422, 'Danh sach thu cung khong hop le.');
    }

    private function ensureRoomBelongsToBranch(?string $roomId, string $branchId): void
    {
        if (! $roomId) {
            return;
        }

        $isValidRoom = Room::where('room_id', $roomId)
            ->where('branch_id', $branchId)
            ->exists();

        abort_if(! $isValidRoom, 422, 'Phong khong thuoc chi nhanh da chon.');
    }

    private function ensurePetsAreAvailableForBooking(array $petIds, string $checkin, string $checkout): void
    {
        $uniquePetIds = collect($petIds)
            ->map(fn ($petId): int => (int) $petId)
            ->filter(fn (int $petId): bool => $petId > 0)
            ->unique()
            ->values();

        if ($uniquePetIds->isEmpty()) {
            return;
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

        abort_if(
            $currentRoomConflicts->isNotEmpty(),
            422,
            sprintf(
                '%s dang o trong phong khac. Vui long checkout thu cung truoc khi them vao booking moi.',
                $this->petConflictNames($currentRoomConflicts)
            )
        );

        $checkinAt = Carbon::parse($checkin);
        $checkoutAt = Carbon::parse($checkout);

        $bookingConflicts = DB::table('booking_room_pet')
            ->join('booking_room', 'booking_room_pet.booking_room_id', '=', 'booking_room.booking_room_id')
            ->join('booking', 'booking_room.booking_id', '=', 'booking.booking_id')
            ->join('pet', 'booking_room_pet.pet_id', '=', 'pet.pet_id')
            ->whereIn('booking_room_pet.pet_id', $uniquePetIds->all())
            ->whereIn('booking.status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])
            ->where('booking.checkin_expected_at', '<', $checkoutAt->toDateTimeString())
            ->where('booking.checkout_expected_at', '>', $checkinAt->toDateTimeString())
            ->select([
                'booking.checkin_expected_at',
                'booking.checkout_expected_at',
                'pet.pet_id',
                'pet.pet_name',
            ])
            ->orderBy('booking.checkin_expected_at')
            ->get()
            ->unique('pet_id')
            ->values();

        if ($bookingConflicts->isEmpty()) {
            return;
        }

        $firstConflict = $bookingConflicts->first();

        abort(422, sprintf(
            '%s dang co booking khac trong khoang %s - %s. Vui long chon thu cung hoac ngay luu tru khac.',
            $this->petConflictNames($bookingConflicts),
            Carbon::parse($firstConflict->checkin_expected_at)->format('d/m/Y H:i'),
            Carbon::parse($firstConflict->checkout_expected_at)->format('d/m/Y H:i')
        ));
    }

    private function petConflictNames($conflicts): string
    {
        return $conflicts
            ->map(fn ($conflict): string => $conflict->pet_name ?: 'Thu cung #'.$conflict->pet_id)
            ->implode(', ');
    }

}
