<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuthorTrackingToMedicalReports extends Migration
{
    public function up(): void
    {
        $fields = [];
        if (! $this->db->fieldExists('created_by_user_id', 'medical_reports')) {
            $fields['created_by_user_id'] = [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'doctor_user_id',
            ];
        }
        if (! $this->db->fieldExists('updated_by_user_id', 'medical_reports')) {
            $fields['updated_by_user_id'] = [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'created_by_user_id',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('medical_reports', $fields);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('updated_by_user_id', 'medical_reports')) {
            $this->forge->dropColumn('medical_reports', 'updated_by_user_id');
        }
        if ($this->db->fieldExists('created_by_user_id', 'medical_reports')) {
            $this->forge->dropColumn('medical_reports', 'created_by_user_id');
        }
    }
}
