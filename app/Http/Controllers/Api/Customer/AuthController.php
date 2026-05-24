<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const TOKEN_CACHE_PREFIX = 'customer_api_token:';
    private const TOKEN_TTL_HOURS = 24;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = User::with('customer')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email hoac mat khau khong dung.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Tai khoan da bi khoa.',
            ], 403);
        }

        if (! $user->customer) {
            return response()->json([
                'message' => 'Tai khoan nay khong phai customer.',
            ], 403);
        }

        $token = Str::random(80);

        Cache::put(
            self::TOKEN_CACHE_PREFIX.$token,
            $user->id,
            now()->addHours(self::TOKEN_TTL_HOURS)
        );

        $user->update([
            'last_login_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dang nhap thanh cong.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => self::TOKEN_TTL_HOURS * 60 * 60,
            'user' => $user,
            'customer' => $user->customer,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:customer,phone'],
            'address' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = DB::transaction(function () use ($validated): Customer {
            $user = User::create([
                'name' => $validated['full_name'],
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => 'CUSTOMER',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
            ]);

            return $customer->fresh('user');
        });

        return response()->json([
            'message' => 'Dang ky tai khoan thanh cong.',
            'data' => $customer,
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token || ! Cache::has(self::TOKEN_CACHE_PREFIX.$token)) {
            return response()->json([
                'message' => 'Token khong hop le hoac da het han.',
            ], 401);
        }

        Cache::forget(self::TOKEN_CACHE_PREFIX.$token);

        return response()->json([
            'message' => 'Dang xuat thanh cong.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Email khong ton tai trong he thong.',
            ], 404);
        }

        $customer = $user->customer;

        if (! $customer) {
            return response()->json([
                'message' => 'Email nay chua co tai khoan dang nhap.',
            ], 404);
        }

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        $resetUrl = url('/auth/reset-password?email='.urlencode($user->email).'&token='.$plainToken);

        try {
            Mail::raw(
                "Nhan vao lien ket sau de dat lai mat khau: {$resetUrl}",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Dat lai mat khau Pet Hotel');
                }
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Khong the gui email dat lai mat khau.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Lien ket dat lai mat khau da duoc gui qua email.',
        ]);
    }

}
