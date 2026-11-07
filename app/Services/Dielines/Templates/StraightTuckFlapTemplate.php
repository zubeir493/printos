<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsCartonProfiles;
use App\Services\Dielines\DielineTemplateContract;

class StraightTuckFlapTemplate implements DielineTemplateContract
{
    use BuildsCartonProfiles;

    public function key(): string
    {
        return 'straight-tuck-flap';
    }

    public function name(): string
    {
        return 'Straight Tuck Carton';
    }

    public function description(): string
    {
        return 'A carton with both rounded tuck closures on the same face, shaped dust flaps and a glued side seam.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/37101/tuck-end-boxes-becf-10301';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_3710120260912-2-4986vj.svg';
    }

    public function defaults(): array
    {
        return ['l' => 65, 'w' => 35, 'h' => 110, 'tuck_flap' => 12, 'glue_flap' => 10];
    }
}
