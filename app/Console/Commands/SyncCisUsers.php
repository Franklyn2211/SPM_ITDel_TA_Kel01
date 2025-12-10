<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\CisClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncCisUsers extends Command
{
    protected $signature = 'cis:sync-users {--per-page=500}';
    protected $description = 'Sync users from CIS directory (dosen/pegawai) into users by cis_user_id';

    public function handle(): int
    {
        $client = new CisClient();
        $perPage = (int) $this->option('per-page');
        $now = now();
        $buffer = [];

        $map = function (array $r) use ($now) {
            $cisId = (string) (data_get($r, 'user_id') ?? '');
            if (!$cisId)
                return null;

            $username = 'cis_' . $cisId;
            $name = data_get($r, 'nama') ?? data_get($r, 'name') ?? $username;

            // Perbaiki email: jika kosong, '-', null, atau tidak valid → generate dummy unik
            $rawEmail = trim((string) (data_get($r, 'email') ?? ''));
            $email = null;
            if ($rawEmail && $rawEmail !== '-') {
                // Ambil email valid pertama jika ada beberapa
                $emails = preg_split('/[;,]/', $rawEmail);
                foreach ($emails as $e) {
                    $e = trim($e);
                    if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $email = $e;
                        break;
                    }
                }
            }
            if (!$email) {
                $email = "u_{$username}@cis.local";
            }

            if ($username === 'adminspm')
                return null;

            return [
                'cis_user_id' => $cisId,
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $flush = function (array &$rows) {
            if (!$rows)
                return;
            // upsert by cis_user_id (stabil)
            User::upsert($rows, ['cis_user_id'], ['username', 'name', 'email', 'password', 'active', 'updated_at']);
            $this->line('  upsert ' . count($rows));
            $rows = [];
        };

        // Pegawai
        $this->info('Sync Pegawai…');
        foreach ($client->getAll(env('CIS_EMPLOYEES_PATH', '/library-api/pegawai'), ['per_page' => $perPage], 'data.pegawai') as $row) {
            if (data_get($row, 'status_pegawai') !== 'A') {
                continue;
            }
            if ($m = $map($row)) {
                $buffer[] = $m;
                if (count($buffer) >= 1000)
                    $flush($buffer);
            }
        }
        $flush($buffer);

        $this->info('Selesai sync direktori → users (by cis_user_id).');
        return self::SUCCESS;
    }
}
