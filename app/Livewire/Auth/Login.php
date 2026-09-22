<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Masuk')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;

    public function masuk(): void
    {
        $this->validate();

        // Borang log masuk ini terdedah kepada internet terbuka. Tanpa had,
        // satu skrip boleh mencuba kata laluan tanpa henti.
        $key = 'masuk:'.mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak cubaan. Cuba lagi dalam '
                    .ceil(RateLimiter::availableIn($key) / 60).' minit.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 300);

            // Satu mesej untuk kedua-dua emel salah dan kata laluan salah.
            // Kalau dibezakan, sesiapa boleh menyenaraikan emel yang berdaftar.
            throw ValidationException::withMessages([
                'email' => 'Emel atau kata laluan tak betul.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectIntended(route('ad-sets.create'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
