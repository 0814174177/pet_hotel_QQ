<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function account(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->currentCustomer($request),
        ]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($customer->user_id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $customer->fill(Arr::only($validated, [
            'full_name',
            'phone',
            'address',
        ]));

        if (array_key_exists('note', $validated)) {
            $customer->notes = $validated['note'];
        }

        $customer->save();

        if ($customer->user) {
            $customer->user->update([
                'name' => $validated['full_name'] ?? $customer->user->name,
                'email' => array_key_exists('email', $validated)
                    ? strtolower(trim($validated['email']))
                    : $customer->user->email,
            ]);
        }

        return response()->json([
            'message' => 'Cap nhat thong tin ca nhan thanh cong.',
            'data' => $customer->fresh('user'),
        ]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'data' => $customer->bookings()
                ->with(['branch', 'rooms', 'bookingServicesPet'])
                ->orderByDesc('checkin_expected_at')
                ->get(),
        ]);
    }

    public function pets(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'data' => $customer->pets()
                ->orderBy('pet_name')
                ->get(),
        ]);
    }

    public function storePet(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $validated = $request->validate([
            'pet_name' => ['required', 'string', 'max:255'],
            'species' => ['required', Rule::in(['DOG', 'CAT', 'BIRD', 'RABBIT', 'OTHER'])],
            'breed' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', Rule::in(['MALE', 'FEMALE', 'UNKNOWN'])],
            'weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:999.99'],
            'special_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $pet = Pet::create([
            'customer_id' => $customer->customer_id,
            'pet_name' => $validated['pet_name'],
            'species' => $validated['species'],
            'breed' => $validated['breed'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'weight_kg' => $validated['weight_kg'] ?? null,
            'special_notes' => $validated['special_note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Them thu cung moi thanh cong.',
            'data' => $pet,
        ], 201);
    }

    private function currentCustomer(Request $request): Customer
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Ban chua dang nhap.');

        return Customer::with('user')
            ->where('user_id', $user->id)
            ->firstOrFail();
    }
}
