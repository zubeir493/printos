<?php

namespace App\Models;

use Database\Factories\DielineImpositionFactory;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DielineImposition extends Model
{
    /** @use HasFactory<DielineImpositionFactory> */
    use HasFactory;

    protected $fillable = [
        'dieline_id', 'user_id', 'name', 'paper_size', 'paper_width', 'paper_height',
        'margin', 'gap', 'horizontal_gap', 'vertical_gap', 'minimum_parallel_clearance', 'status', 'source_geometry', 'placements', 'copies',
        'utilisation', 'disk', 'files', 'error_message', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['source_geometry' => 'array', 'placements' => 'array', 'files' => 'array', 'completed_at' => 'datetime', 'margin' => 'float', 'gap' => 'float', 'horizontal_gap' => 'float', 'vertical_gap' => 'float', 'minimum_parallel_clearance' => 'float', 'utilisation' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dieline(): BelongsTo
    {
        return $this->belongsTo(Dieline::class);
    }

    public function downloadUrl(string $format): string
    {
        return route('dieline-impositions.download', ['dielineImposition' => $this, 'format' => $format], absolute: false);
    }

    public function sheetLabel(): string
    {
        return $this->paper_size === 'custom'
            ? $this->paper_width.' × '.$this->paper_height.' mm'
            : strtoupper($this->paper_size);
    }

    public function completedNotification(): Notification
    {
        return Notification::make()->success()->persistent()->title('Imposition ready')
            ->body($this->copies.' copies on '.$this->sheetLabel().'. Downloads are at 100% scale.')
            ->actions(array_map(fn (string $format): Action => Action::make($format)->label(strtoupper($format))->url($this->downloadUrl($format))->extraAttributes(['download' => true]), ['pdf', 'svg', 'dxf']));
    }
}
