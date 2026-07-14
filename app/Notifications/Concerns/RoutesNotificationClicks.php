<?php

namespace App\Notifications\Concerns;

use App\UserRole;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Route;

trait RoutesNotificationClicks
{
    protected function notificationUrl(object $notifiable): string
    {
        return $this->panelDashboardUrl($notifiable);
    }

    /**
     * @return array<int, Action>
     */
    protected function databaseActions(object $notifiable, string $label = 'Open'): array
    {
        return [
            Action::make('view')
                ->label($label)
                ->url($this->notificationUrl($notifiable))
                ->markAsRead(),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function resourceUrl(object $notifiable, string $resource, string $page, array $parameters = []): string
    {
        foreach ($this->candidatePanelIds($notifiable) as $panelId) {
            $routeName = "filament.{$panelId}.resources.{$resource}.{$page}";

            if (Route::has($routeName)) {
                return route($routeName, $parameters);
            }
        }

        return $this->panelDashboardUrl($notifiable);
    }

    protected function panelDashboardUrl(object $notifiable): string
    {
        $role = $notifiable->role ?? null;

        if ($role instanceof UserRole) {
            return url($role->getRedirectPath());
        }

        return url('/');
    }

    /**
     * @return array<int, string>
     */
    private function candidatePanelIds(object $notifiable): array
    {
        $role = $notifiable->role ?? null;

        if ($role instanceof UserRole) {
            return [$role->value];
        }

        if (is_string($role) && filled($role)) {
            return [$role];
        }

        return [];
    }
}
