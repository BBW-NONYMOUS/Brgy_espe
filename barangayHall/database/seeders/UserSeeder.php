<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Starter accounts. Every account must change its password on first login.
     *
     * @var list<array{username: string, name: string, role: RoleName}>
     */
    public const ACCOUNTS = [
        ['username' => 'admin', 'name' => 'Ma. Teresa Alonzo', 'role' => RoleName::Admin],
        ['username' => 'bhw.analiza', 'name' => 'Analiza Sumagaysay', 'role' => RoleName::Bhw],
        ['username' => 'bhw.rosario', 'name' => 'Rosario Palmares', 'role' => RoleName::Bhw],
        ['username' => 'mw.cristina', 'name' => 'Cristina Gelito, RM', 'role' => RoleName::Midwife],
        ['username' => 'kgd.ronaldo', 'name' => 'Hon. Ronaldo Catalan', 'role' => RoleName::Official],
    ];

    public function run(): void
    {
        $password = config('app.seed_default_password');

        foreach (self::ACCOUNTS as $account) {
            User::query()->updateOrCreate(['username' => $account['username']], [
                'name' => $account['name'],
                'role_id' => Role::for($account['role'])->id,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);
        }
    }
}
