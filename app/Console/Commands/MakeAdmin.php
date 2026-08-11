<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:make {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote an existing user to the admin role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->update(['role' => 'admin', 'is_active' => true]);

        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
