<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCenterIdToStudies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('studies', [
            'center_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('studies', 'center_id');
    }
}
