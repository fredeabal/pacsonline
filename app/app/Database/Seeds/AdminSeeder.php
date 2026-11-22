<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => 'admin@demo.com']) !== null) {
            return;
        }

        $user = new User([
            'username' => 'admin_demo',
            'email'    => 'admin@demo.com',
            'password' => 'admin123',
        ]);

        $users->save($user);

        $savedUser = $users->findById($users->getInsertID());
        if ($savedUser !== null) {
            $savedUser->activate();
            $savedUser->addGroup('admin');
        }
    }
}
