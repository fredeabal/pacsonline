<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OrthancClient;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Endpoint de estado de salud del portal y conectividad con Orthanc.          //
/////////////////////////////////////////////////////////////////////////////////
class HealthController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Devuelve JSON con el estado de salud del portal y Orthanc (200 / 503).  //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): ResponseInterface
    {
        $orthancOk = (new OrthancClient())->isAlive();

        return $this->response
            ->setStatusCode($orthancOk ? 200 : 503)
            ->setJSON([
                'status'  => $orthancOk ? 'ok' : 'degraded',
                'orthanc' => $orthancOk,
                'time'    => gmdate('c'),
            ]);
    }
}
