<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/**
 * Modelo de usuarios extendido sobre Shield con datos de perfil médico y firma.
 */
class PacsUserModel extends ShieldUserModel
{
    protected function initialize(): void
    {
        parent::initialize();

        $this->allowedFields = [
            ...$this->allowedFields,
            'full_name',
            'specialty',
            'license_number',
            'signature_file',
        ];
    }
}
