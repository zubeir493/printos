<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsCartonProfiles;
use App\Services\Dielines\DielineTemplateContract;

class AutoBottomTuckTopTemplate implements DielineTemplateContract
{
    use BuildsCartonProfiles;

    public function key(): string
    {
        return 'auto-bottom-tuck-top';
    }

    public function name(): string
    {
        return 'Auto Bottom Carton';
    }

    public function description(): string
    {
        return 'A tuck-top carton with a pre-glued crash-lock base, diagonal folding wings and a side seam.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/45291/tuck-top-auto-bottom-boxes-becf-11101';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_4529120260912-2-43j91g.svg';
    }

    public function defaults(): array
    {
        return ['l' => 70, 'w' => 45, 'h' => 110, 'tuck_flap' => 15, 'glue_flap' => 12];
    }
}
