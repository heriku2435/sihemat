<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold text-gray-800">Masuk ke Akun</h2>
        <p class="text-gray-500 mt-1">Silakan masukkan kredensial Anda</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
            <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" 
                class="w-full rounded-2xl bg-gray-50 border border-gray-200 px-4 py-3.5 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 transition shadow-sm placeholder-gray-400"
                placeholder="admin@sihemat.com">
            <x-input-error :messages="$errors->get('form.email')" class="mt-2 text-red-500 text-sm" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
            <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password"
                class="w-full rounded-2xl bg-gray-50 border border-gray-200 px-4 py-3.5 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 transition shadow-sm placeholder-gray-400"
                placeholder="••••••••">
            <x-input-error :messages="$errors->get('form.password')" class="mt-2 text-red-500 text-sm" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between mt-2">
            <label for="remember" class="inline-flex items-center cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500 w-5 h-5 transition" name="remember">
                <span class="ms-2 text-sm text-gray-600 font-medium">Ingat Saya</span>
            </label>
            
            @if (Route::has('password.request'))
                <a class="text-sm font-semibold text-emerald-600 hover:text-emerald-500 transition" href="{{ route('password.request') }}" wire:navigate>
                    Lupa Password?
                </a>
            @endif
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-4 rounded-2xl shadow-xl shadow-emerald-500/30 transition transform hover:-translate-y-1">
                Log In Sekarang
            </button>
        </div>
    </form>
</div>
