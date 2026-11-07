<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsCartonProfiles;
use App\Services\Dielines\DielineTemplateContract;

class SnapLockBottomTemplate implements DielineTemplateContract
{
    use BuildsCartonProfiles;

    public function key(): string
    {
        return 'snap-lock-bottom-tuck-top';
    }

    public function name(): string
    {
        return 'Snap Lock Carton';
    }

    public function description(): string
    {
        return 'A tuck-top carton with four interlocking bottom flaps that close in sequence and a glued side seam.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/150765/snap-lock-boxes-becf-10c02';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_15076520260912-2-qg5t7w.svg';
    }

    public function defaults(): array
    {
        return ['l' => 80, 'w' => 45, 'h' => 120, 'tuck_flap' => 15, 'glue_flap' => 12];
    }
}
