<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Daftar')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public function daftar(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Isi nama anda.',
            'email.required' => 'Isi emel anda.',
            'email.unique' => 'Emel ni dah ada akaun. Cuba masuk.',
            'phone.required' => 'Isi nombor WhatsApp anda.',
            'password.min' => 'Kata laluan kena sekurang-kurangnya 8 aksara.',
        ]);

        $phone = Phone::normalise($data['phone']);

        if ($phone === null) {
            $this->addError('phone', 'Nombor tu nampak tak betul. Contoh: 012-345 6789.');

            return;
        }

        if (User::where('phone', $phone)->exists()) {
            $this->addError('phone', 'Nombor ni dah ada akaun. Cuba masuk.');

            return;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            'password' => $data['password'],
            // is_owner tidak pernah datang dari borang. Hanya arahan
            // dynoads:owner boleh menetapkannya — kalau tidak, sesiapa yang
            // mendaftar boleh membelanjakan duit pemilik app.
        ]);

        Auth::login($user, remember: true);
        session()->regenerate();

        $this->redirect(route('meta.connect'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
