<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('admin:create')]
#[Description('Buat akun untuk mengakses halaman administrasi')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $name = trim((string) $this->ask('Nama admin'));
        $email = Str::lower(trim((string) $this->ask('Email admin')));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Nama dan alamat email yang valid wajib diisi.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Email tersebut sudah digunakan.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Kata sandi admin');
        $passwordConfirmation = (string) $this->secret('Ulangi kata sandi');

        if (strlen($password) < 12 || ! hash_equals($password, $passwordConfirmation)) {
            $this->error('Kata sandi harus minimal 12 karakter dan kedua input harus sama.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info('Akun admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
