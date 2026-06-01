<?php

namespace App\Http\Controllers;

use App\Models\Proforma;
use App\Services\Proformas\ProformaPdfService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProformaDownloadController extends Controller
{
    public function __invoke(Proforma $proforma): BinaryFileResponse
    {
        return app(ProformaPdfService::class)->downloadResponse($proforma);
    }
}
