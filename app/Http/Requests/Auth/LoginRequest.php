<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Vui lòng nhập tên đăng nhập, số điện thoại hoặc email.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }

    /**
     * Đăng nhập bằng tên đăng nhập, số điện thoại HOẶC email.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login')->toString());
        $password = $this->string('password')->toString();

        // Thử lần lượt: email -> tên đăng nhập -> số điện thoại (10 số, có thể có dấu chấm/cách)
        $attempts = [];
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $attempts[] = ['email' => strtolower($login)];
        } else {
            $attempts[] = ['username' => strtolower($login)];

            $digits = preg_replace('/[\s.\-]/', '', $login);
            if (preg_match('/^\d{10}$/', $digits)) {
                $attempts[] = ['phone' => $digits];
            }
        }

        foreach ($attempts as $credentials) {
            if (Auth::attempt($credentials + ['password' => $password], $this->boolean('remember'))) {
                RateLimiter::clear($this->throttleKey());

                return;
            }
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => 'Tên đăng nhập, số điện thoại, email hoặc mật khẩu không đúng.',
        ]);
    }

    /**
     * Chặn dò mật khẩu: tối đa 5 lần sai liên tiếp.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')->toString()).'|'.$this->ip());
    }
}
