<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPriorityAndDoctorToStudies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('studies', [
            'priority' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'normal',
            ],
            'assigned_doctor_id' => [
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

        $this->forge->dropColumn('studies', ['priority', 'assigned_doctor_id']);
    }
}
