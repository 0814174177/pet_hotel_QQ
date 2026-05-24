<?php

namespace App\Http\Requests\Web\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branch,branch_id'],
            'room_type' => ['required', 'integer', 'exists:type_room,type_room_id'],
            'checkin_expected_at' => ['required', 'date'],
            'checkout_expected_at' => ['required', 'date', 'after:checkin_expected_at'],
            'pet_ids' => ['required', 'array', 'min:1'],
            'pet_ids.*' => ['required', 'integer', 'distinct', 'exists:pet,pet_id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['required', 'integer', 'distinct', 'exists:services,service_id'],
            'service_pet_ids' => ['nullable', 'array'],
            'service_pet_ids.*' => ['nullable', 'array'],
            'service_pet_ids.*.*' => ['required', 'integer', 'exists:services,service_id'],
            'booking_action' => ['nullable', 'in:pay,hold'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Vui lòng chọn chi nhánh.',
            'branch_id.exists' => 'Chi nhánh không hợp lệ.',
            'room_type.required' => 'Vui lòng chọn loại phòng trước khi xem lịch trống.',
            'room_type.exists' => 'Loại phòng không hợp lệ.',
            'checkin_expected_at.required' => 'Vui lòng chọn ngày nhận phòng.',
            'checkout_expected_at.required' => 'Vui lòng chọn ngày trả phòng.',
            'checkout_expected_at.after' => 'Ngày trả phòng phải sau ngày nhận phòng.',
            'pet_ids.required' => 'Vui lòng chọn thú cưng trước khi thanh toán.',
            'pet_ids.min' => 'Vui lòng chọn thú cưng trước khi thanh toán.',
            'pet_ids.*.exists' => 'Thú cưng được chọn không hợp lệ.',
        ];
    }

    public function branchId(): int
    {
        return (int) $this->validated()['branch_id'];
    }

    public function roomTypeId(): int
    {
        return (int) $this->validated()['room_type'];
    }

    public function checkinAt(): string
    {
        return (string) $this->validated()['checkin_expected_at'];
    }

    public function checkoutAt(): string
    {
        return (string) $this->validated()['checkout_expected_at'];
    }

    public function petIds(): array
    {
        return collect($this->validated()['pet_ids'])
            ->map(fn (int|string $petId): int => (int) $petId)
            ->unique()
            ->values()
            ->all();
    }

    public function serviceIds(): array
    {
        return collect($this->validated()['service_ids'] ?? [])
            ->map(fn (int|string $serviceId): int => (int) $serviceId)
            ->unique()
            ->values()
            ->all();
    }

    public function servicePetIds(array $petIds): array
    {
        $selectedPetIds = collect($petIds)
            ->map(fn (int|string $petId): int => (int) $petId)
            ->unique()
            ->values();

        $mappedServices = collect($this->validated()['service_pet_ids'] ?? [])
            ->mapWithKeys(fn (array $serviceIds, int|string $petId): array => [
                (int) $petId => collect($serviceIds)
                    ->map(fn (int|string $serviceId): int => (int) $serviceId)
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->only($selectedPetIds->all())
            ->filter(fn (array $serviceIds): bool => $serviceIds !== [])
            ->all();

        if ($mappedServices !== []) {
            return $mappedServices;
        }

        $legacyServiceIds = $this->serviceIds();

        return $legacyServiceIds === [] ? [] : [
            $selectedPetIds->first() => $legacyServiceIds,
        ];
    }

    public function isHoldOnly(): bool
    {
        return ($this->validated()['booking_action'] ?? 'pay') === 'hold';
    }

    public function bookingPayload(): array
    {
        $petIds = $this->petIds();

        return [
            'branch_id' => $this->branchId(),
            'room_type' => $this->roomTypeId(),
            'pet_id' => $petIds[0],
            'pet_ids' => $petIds,
            'checkin_expected_at' => $this->checkinAt(),
            'checkout_expected_at' => $this->checkoutAt(),
            'service_ids' => $this->serviceIds(),
            'service_pet_ids' => $this->servicePetIds($petIds),
        ];
    }
}
