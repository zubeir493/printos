<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Services\JobOrderPrintPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class JobOrderPrintController extends Controller
{
    public function __invoke(JobOrder $jobOrder, JobOrderPrintPdf $printPdf): Response
    {
        Gate::authorize('view', $jobOrder);

        return $printPdf->download($jobOrder);
    }
}
