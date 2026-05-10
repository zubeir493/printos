<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class PrimaryColorAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_strtoupper(mb_substr($segment, 0, 1)) : '')
            ->join('+');

        // Indigo-600 (#4f46e5) — matches the primary color set in all panel providers
        // bold=true makes the initial heavier; font-size=0.5 gives a larger letter
        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&format=svg&color=FFFFFF&background=4f46e5&bold=true&font-size=0.5';
    }
}
