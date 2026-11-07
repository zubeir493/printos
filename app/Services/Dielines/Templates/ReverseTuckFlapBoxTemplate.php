<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsCartonProfiles;
use App\Services\Dielines\DielineTemplateContract;

class ReverseTuckFlapBoxTemplate implements DielineTemplateContract
{
    use BuildsCartonProfiles;

    public function key(): string
    {
        return 'reverse-tuck-flap-box';
    }

    public function name(): string
    {
        return 'Reverse Tuck Carton';
    }

    public function description(): string
    {
        return 'A pharmacy carton with rounded tuck closures on opposite faces, relieved dust flaps and a glued side seam.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/109431/tuck-end-boxes-becf-10803';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_10943120260912-2-cplhdu.svg';
    }

    public function defaults(): array
    {
        return ['l' => 60, 'w' => 30, 'h' => 110, 'tuck_flap' => 12, 'glue_flap' => 10];
    }
}
