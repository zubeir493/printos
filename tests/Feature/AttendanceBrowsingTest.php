<?php

use App\Filament\Resources\AttendanceSegments\Pages\ManageAttendanceSegments;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\NotifyUnmatchedAttendanceNumbers;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->employee = Employee::factory()->create(['first_name' => 'Abas', 'last_name' => 'Seman', 'employee_id' => 'STAFF-ABC', 'attendance_device_id' => '00995']);
    $other = Employee::factory()->create(['first_name' => 'Mimi', 'last_name' => 'Kebede', 'employee_id' => 'STAFF-XYZ', 'attendance_device_id' => '00888']);
    $this->record = AttendanceSegment::create(['employee_id' => $this->employee->id, 'date' => '2026-11-09', 'fp_no' => 'OLD-0042', 'status' => 'Present']);
    $this->otherRecord = AttendanceSegment::create(['employee_id' => $other->id, 'date' => '2026-11-09', 'fp_no' => '00888', 'status' => 'Present']);
});

it('shows all employees attendance by default with a search input', function (): void {
    Livewire::test(ManageAttendanceSegments::class)->assertCanSeeTableRecords([$this->record, $this->otherRecord])
        ->assertSee('Search name, employee ID or FP number')->assertDontSee('selectedEmployeeId');
});

it('searches attendance by employee names identifiers and historical FP numbers', function (string $search): void {
    Livewire::test(ManageAttendanceSegments::class)->searchTable($search)
        ->assertCanSeeTableRecords([$this->record])->assertCanNotSeeTableRecords([$this->otherRecord]);
})->with(['Abas', 'Seman', 'Abas Seman', 'STAFF-ABC', '00995', 'OLD-0042']);

it('optionally filters attendance to an employee and resets back to everyone', function (): void {
    Livewire::test(ManageAttendanceSegments::class)->filterTable('employee', $this->employee->id)
        ->assertCanSeeTableRecords([$this->record])->assertCanNotSeeTableRecords([$this->otherRecord])
        ->resetTableFilters()->assertCanSeeTableRecords([$this->record, $this->otherRecord]);
});

it('shows the actual unmatched FP list with counts and excludes already mapped numbers', function (): void {
    AttendanceDevicePunch::factory()->count(2)->create(['device_id' => 'office-mb360', 'device_user_id' => '00077']);
    AttendanceDevicePunch::factory()->create(['device_id' => 'office-mb360', 'device_user_id' => '00995']);
    $component = Livewire::test(ManageAttendanceSegments::class)->mountAction('unmatchedNumbers')->assertActionMounted('unmatchedNumbers');
    expect($component->instance()->getMountedAction()->getModalContent()->render())->toContain('00077', 'Punches', 'Last punch');
    $rows = NotifyUnmatchedAttendanceNumbers::unmatchedRows('office-mb360');
    expect($rows)->toHaveCount(1)->and($rows->first()->punch_count)->toBe(2);
});

it('deep links notifications into the right panel and repairs older notification links without losing read status', function (): void {
    AttendanceDevicePunch::factory()->create(['device_id' => 'office-mb360', 'device_user_id' => '00077']);
    $hr = User::factory()->create(['role' => UserRole::HR]);
    app(NotifyUnmatchedAttendanceNumbers::class)->handle('office-mb360');
    foreach ([auth()->user(), $hr] as $recipient) {
        $notification = $recipient->notifications()->sole();
        $expectedPath = $recipient->role === UserRole::Admin ? '/attendance-segments' : '/hr/attendance-segments';
        expect(parse_url($notification->data['actions'][0]['url'], PHP_URL_PATH))->toBe($expectedPath)
            ->and($notification->data['actions'][0]['url'])->toContain('action=unmatchedNumbers');
        $old = $notification->data;
        $old['actions'][0]['url'] = 'http://localhost'.$expectedPath;
        $notification->update(['data' => $old, 'read_at' => now()]);
    }
    expect(app(NotifyUnmatchedAttendanceNumbers::class)->repairExistingNotifications())->toBe(2);
    expect(app(NotifyUnmatchedAttendanceNumbers::class)->repairExistingNotifications())->toBe(0);
    foreach ([auth()->user(), $hr] as $recipient) {
        $notification = $recipient->notifications()->sole();
        expect($notification->read_at)->not->toBeNull()
            ->and($notification->data['actions'][0]['url'])->toContain('action=unmatchedNumbers');
    }
});

it('opens a helpful empty state after FP numbers have been resolved', function (): void {
    $component = Livewire::test(ManageAttendanceSegments::class)->mountAction('unmatchedNumbers')->assertActionMounted('unmatchedNumbers');
    expect($component->instance()->getMountedAction()->getModalContent()->render())->toContain('All FP numbers are matched.');
});
