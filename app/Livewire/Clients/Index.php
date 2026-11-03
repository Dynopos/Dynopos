<?php

namespace App\Livewire\Clients;

use App\Models\User;
use App\Support\Impersonation;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Skrin "Pelanggan" — pemilik sahaja.
 *
 * Servis urus iklan sebelum App Review lulus: setiap pelanggan ialah satu akaun
 * user biasa, dan pemilik "masuk sebagai" akaun itu untuk menyambung Page,
 * merekod persetujuan dan membuat iklan. Pengasingan Fasa 7a kekal utuh kerana
 * tiada laluan khas — pemilik nampak tepat apa yang pelanggan itu nampak.
 */
#[Layout('layouts.app')]
#[Title('Pelanggan')]
class Index extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $showForm = false;

    #[Computed]
    public function clients()
    {
        return User::query()
            ->where('is_owner', false)
            ->with('fbConnection')
            ->orderBy('name')
            ->get();
    }

    public function tambah(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['required', 'string'],
        ], [
            'name.required' => 'Isi nama kedai atau pemilik.',
            'email.required' => 'Isi emel pelanggan.',
            'email.unique' => 'Emel ni dah ada akaun.',
            'phone.required' => 'Isi nombor telefon pelanggan.',
        ]);

        $phone = Phone::normalise($data['phone']);

        if ($phone === null) {
            $this->addError('phone', 'Nombor tu nampak tak betul. Contoh: 012-345 6789.');

            return;
        }

        if (User::where('phone', $phone)->exists()) {
            $this->addError('phone', 'Nombor ni dah ada akaun.');

            return;
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            // Pelanggan tidak log masuk sendiri buat masa ini. Kata laluan rawak
            // yang tiada siapa tahu lebih selamat daripada kata laluan lalai.
            'password' => Str::password(32),
        ]);

        $this->reset('name', 'email', 'phone', 'showForm');
        unset($this->clients);
    }

    public function urus(int $userId)
    {
        $client = User::where('is_owner', false)->findOrFail($userId);

        Impersonation::start(Auth::user(), $client);

        return $this->redirectRoute(
            $client->hasMetaCredentials() ? 'ad-sets.create' : 'meta.connect',
            navigate: false,
        );
    }

    public function render()
    {
        return view('livewire.clients.index');
    }
}
