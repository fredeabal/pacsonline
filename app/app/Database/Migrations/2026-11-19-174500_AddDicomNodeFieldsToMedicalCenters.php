<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDicomNodeFieldsToMedicalCenters extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('medical_centers', [
            'can_receive' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'active',
            ],
            'can_send' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'can_receive',
            ],
            'dicom_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'can_send',
            ],
            'dicom_port' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'dicom_host',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('medical_centers', [
            'can_receive',
            'can_send',
            'dicom_host',
            'dicom_port',
        ]);
    }
}
