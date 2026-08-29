<?php

namespace App\Console\Commands;

use App\Concerns\AdminPasswordValidationRules;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    use AdminPasswordValidationRules;

    protected $signature = 'admin:create {name} {email} {password}';

    protected $description = 'Create a new admin account for the /admin dashboard';

    public function handle(): int
    {
        $validator = Validator::make(
            [
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'password' => $this->argument('password'),
                // このコマンドは `{password}` 引数のみを受け取り、確認用の引数は存在しない。
                // passwordRules() に含まれる `confirmed` ルールを満たすため、同じ値を
                // password_confirmation として渡す。これを省略すると
                // password_confirmation キーが存在せず `confirmed` が常に失敗する。
                'password_confirmation' => $this->argument('password'),
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
                'password' => $this->passwordRules(),
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
        ]);

        $this->info("Admin account created: {$admin->email}");

        return self::SUCCESS;
    }
}
