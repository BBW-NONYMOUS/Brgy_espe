<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates the first Admin account on a new server (see docs/deployment.md).
 * Every other account is then created by that Admin in Users.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'user:create-admin {username : Login name, e.g. admin.teresa} {--name= : Full name shown in the app}';

    protected $description = 'Create an Admin account with a one-time temporary password';

    public function handle(): int
    {
        $data = [
            'username' => Str::lower(trim((string) $this->argument('username'))),
            'name' => Str::squish((string) ($this->option('name') ?: $this->ask('Full name'))),
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:users'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $role = Role::query()->where('name', RoleName::Admin)->first();

        if ($role === null) {
            $this->components->error('Roles are missing. Run `php artisan db:seed --class=ProductionSeeder --force` first.');

            return self::FAILURE;
        }

        $password = TemporaryPassword::generate();

        User::query()->create([
            ...$data,
            'role_id' => $role->id,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->components->info("Admin account {$data['username']} created.");
        $this->components->twoColumnDetail('Temporary password', $password);
        $this->components->warn('It is shown only once. The Admin must change it at first login.');

        return self::SUCCESS;
    }
}
