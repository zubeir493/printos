<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected bool $hasTopbar = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                ...filled(config('webpush.vapid.public_key'))
                    ? [View::make('filament.profile.webpush-settings')]
                    : [],
            ]);
    }
}
