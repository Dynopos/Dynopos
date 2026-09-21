<?php

namespace App\Console\Commands;

use App\Models\AdSet;
use App\Models\PosterJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cipta akaun pemilik dan tuntut data yang wujud sebelum Fasa 7a.
 *
 * Pangkalan data pengeluaran sudah mengandungi ad_sets dan poster_jobs sebenar
 * yang dibuat ketika app masih untuk seorang. Baris itu mempunyai user_id null
 * dan tidak kelihatan kepada sesiapa. Arahan ini menyerahkannya kepada satu
 * akaun — sekali sahaja, selepas deploy pertama Fasa 7a.
 *
 * is_owner HANYA boleh ditetapkan dari sini, tidak pernah dari borang daftar.
 * Pemilik ialah satu-satunya user yang jatuh balik kepada kredential .env.
 */
class ClaimOwnerCommand extends Command
{
    protected $signature = 'dynoads:owner
                            {email : Emel akaun pemilik}
                            {--name= : Nama pemilik, kalau akaun belum wujud}
                            {--password= : Kata laluan, kalau akaun belum wujud}';

    protected $description = 'Tetapkan akaun pemilik dan tuntut data yang belum bertuan';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = (string) ($this->option('password') ?: '');

            if ($password === '') {
                $password = (string) $this->secret('Kata laluan untuk akaun baharu ini');
            }

            if (mb_strlen($password) < 8) {
                $this->error('Kata laluan kena sekurang-kurangnya 8 aksara.');

                return self::FAILURE;
            }

            $user = User::create([
                'name' => (string) ($this->option('name') ?: 'Pemilik'),
                'email' => $email,
                'password' => $password,
            ]);

            $this->info("Akaun {$email} dicipta.");
        }

        $user->forceFill(['is_owner' => true])->save();

        $claimed = DB::transaction(fn () => [
            'ad_sets' => AdSet::unclaimed()->update(['user_id' => $user->id]),
            'poster_jobs' => PosterJob::unclaimed()->update(['user_id' => $user->id]),
        ]);

        $this->info("{$email} kini pemilik.");
        $this->line("  ad_sets dituntut    : {$claimed['ad_sets']}");
        $this->line("  poster_jobs dituntut: {$claimed['poster_jobs']}");
        $this->newLine();
        $this->comment('Pemilik jatuh balik kepada kredential .env bila tiada FbConnection.');
        $this->comment('Peniaga lain WAJIB sambung akaun Meta sendiri sebelum boleh buat iklan.');

        return self::SUCCESS;
    }
}
