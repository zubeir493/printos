<?php

use App\Filament\Resources\AttendanceSegments\Pages\ManageAttendanceSegments;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('imports attendance from the attendance segments page instead of a separate imports resource', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::HR,
    ]));

    expect(Route::has('filament.hr.resources.attendance-segments.index'))->toBeTrue()
        ->and(Route::has('filament.hr.resources.attendance-imports.index'))->toBeFalse();

    Livewire::test(ManageAttendanceSegments::class)
        ->assertActionVisible('importAttendanceCsv');
});
