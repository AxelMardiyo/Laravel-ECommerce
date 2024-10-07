<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

#[Title('Reset Password')]

class ResetPasswordPage extends Component
{

    public $token;
    #[Url]
    public $email;
    public $password;
    public $password_confirmation;

    public function mount($token) {
        $this->token = $token;
    }

    public function save() {
        $this->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed'
        ]);

        $status = Password::reset([
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'token' => $this->token
        ],

        function(User $user) {
            $user->forceFill([
                'password' => Hash::make($this->password)
            ])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        dd($status);

        return $status === Password::PASSWORD_RESET ? redirect('/login')->with('success', 'Password reset successfully') : session()->flash('error', 'Something went wrong');        
    }

    public function render()
    {
        return view('livewire.auth.reset-password-page');
    }
}
