<?php

namespace App\Http\Controllers;

use App\Models\Proforma;
use App\Services\Proformas\ProformaPdfService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProformaDownloadController extends Controller
{
    public function __invoke(Proforma $proforma): BinaryFileResponse
    {
        Gate::authorize('view', $proforma);

        return app(ProformaPdfService::class)->downloadResponse($proforma);
    }
}
