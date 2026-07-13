<?php

namespace App\Http\Controllers;

use App\Models\AccountingExport;
use App\UserRole;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingExportDownloadController extends Controller
{
    public function __invoke(AccountingExport $accountingExport): StreamedResponse
    {
        abort_unless(in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Finance], true), 403);
        abort_unless($accountingExport->status === AccountingExport::STATUS_COMPLETED && filled($accountingExport->file_path), 404);

        $disk = config('filesystems.private_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($accountingExport->file_path), 404);

        return Storage::disk($disk)->download($accountingExport->file_path, $accountingExport->file_name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
