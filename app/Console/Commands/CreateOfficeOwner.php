<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateOfficeOwner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'office:owner {--email= : The email of the owner}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the Living Office owner account securely';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Living AI Office - Owner Setup');
        $email = $this->option('email') ?: $this->ask('Enter owner email (e.g. admin@example.com)');
        
        $password = $this->secret('Enter a strong password for this owner account');
        $passwordConfirm = $this->secret('Confirm password');

        if ($password !== $passwordConfirm) {
            $this->error('Passwords do not match. Aborting.');
            return 1;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return 1;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Office Owner',
                'password' => Hash::make($password),
            ]
        );

        $this->info("Owner account {$email} has been configured successfully.");
        return 0;
    }
}
