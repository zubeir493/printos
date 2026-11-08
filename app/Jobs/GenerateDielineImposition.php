<?php

namespace App\Jobs;

use App\Models\DielineImposition;
use App\Services\Dielines\DielineExportService;
use App\Services\Dielines\DielineImpositionService;
use App\Support\RequiredStorage;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateDielineImposition implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public function __construct(public int $impositionId)
    {
        $this->onQueue('dielines');
    }

    /**
     * Execute the job.
     */
    public function handle(DielineImpositionService $nesting, DielineExportService $exports): void
    {
        if (! DielineImposition::query()->whereKey($this->impositionId)->where('status', 'queued')->update(['status' => 'processing'])) {
            return;
        }
        $imposition = DielineImposition::query()->findOrFail($this->impositionId);
        try {
            $layout = $nesting->nest($imposition);
            $geometry = $nesting->sheetGeometry($imposition, $layout['placements']);
            $files = [];
            $disk = config('dielines.imposition.disk', 'local');
            $imposition->update(['disk' => $disk]);
            foreach (['svg', 'dxf', 'pdf'] as $format) {
                $path = 'dieline-impositions/'.$imposition->getKey().'/sheet.'.$format;
                RequiredStorage::put($disk, $path, $exports->contents($geometry, $format), ['visibility' => 'private']);
                $files[$format] = $path;
            }
            $imposition->update(['status' => 'completed', 'placements' => $layout['placements'], 'copies' => $layout['copies'], 'utilisation' => $layout['utilisation'], 'files' => $files, 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        }

        try {
            $imposition->user->notifyNow($imposition->completedNotification()->toDatabase());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $imposition = DielineImposition::query()->find($this->impositionId);
        if (! $imposition || in_array($imposition->status, ['completed', 'failed'], true)) {
            return;
        }
        $message = 'Imposition could not be generated. Please try again.';
        if (str_contains($exception?->getMessage() ?? '', 'does not fit')) {
            $message = 'This dieline does not fit on the selected paper at 100% scale. Choose a larger paper size.';
        }
        $imposition->update(['status' => 'failed', 'error_message' => $message]);
        if ($imposition->disk) {
            rescue(fn () => Storage::disk($imposition->disk)->deleteDirectory('dieline-impositions/'.$imposition->getKey()));
        }
        rescue(fn () => $imposition->user->notifyNow(Notification::make()->danger()->title('Imposition failed')->body($message)->toDatabase()));
    }
}
