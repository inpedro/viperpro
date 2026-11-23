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
    protected $signature = 'viper:create-admin {email} {--name=Administrator}';

    protected $description = 'Create a platform administrator (password is prompted, never passed as an argument)';

    public function handle(): int
    {
        $email    = (string) $this->argument('email');
        $password = (string) $this->secret('Password (min 12 characters)');

        $validator = Validator::make(compact('email', 'password'), [
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:12',
        ]);

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

            Role::findOrCreate('admin', 'web');
            $user->assignRole('admin');

            if (! Wallet::where('user_id', $user->id)->exists()) {
                Wallet::create(['user_id' => $user->id, 'active' => 1]);
            }
        });

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
