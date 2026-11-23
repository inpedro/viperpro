<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Creates an administrator interactively. Replaces the admin@demo.com account that used to
 * ship inside sql/viperpro.sql with a publicly known password hash.
 */
class CreateAdmin extends Command
{
    protected $signature = 'viper:create-admin {email} {--name=Administrator} {--tenant=1 : tenant id for a casino admin} {--platform : create a platform super admin (no tenant) instead}';

    protected $description = 'Create a platform administrator (password is prompted, never passed as an argument)';

    public function handle(): int
    {
        if ($this->option('platform')) {
            \App\Core\Tenancy\Tenancy::setPlatform();
        } else {
            \App\Core\Tenancy\Tenancy::set(\App\Core\Tenancy\Models\Tenant::findOrFail((int) $this->option('tenant')));
        }

        $email    = (string) $this->argument('email');
        $password = (string) $this->secret('Password (min 12 characters)');

        $validator = Validator::make(compact('email', 'password'), [
            'email'    => 'required|email',
            'password' => 'required|string|min:12',
        ]);

        if (User::where('email', $email)->exists()) {
            $this->error('A user with this e-mail already exists in this context.');
            return self::FAILURE;
        }

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $password) {
            $user = new User();
            $user->forceFill([
                'name'     => (string) $this->option('name'),
                'email'    => $email,
                'password' => $password,
                'role_id'  => 0,
            ])->save();

            $role = $this->option('platform') ? 'super-admin' : 'admin';
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        });

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
