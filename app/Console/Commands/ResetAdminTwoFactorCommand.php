<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class ResetAdminTwoFactorCommand extends Command
{
    protected $signature = 'admin:reset-two-factor {email}';

    protected $description = 'Clear two-factor authentication for an admin account (recovery when device and codes are both lost)';

    public function handle(): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if (! $admin) {
            $this->error('指定されたメールアドレスのAdminアカウントが見つかりません。');

            return self::FAILURE;
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("Admin {$admin->email} の二段階認証をリセットしました。次回ログイン後、再設定が必要です。");

        return self::SUCCESS;
    }
}
