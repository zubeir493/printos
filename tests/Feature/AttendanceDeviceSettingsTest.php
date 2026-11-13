<?php

use App\Filament\Pages\Settings;
use App\Jobs\SyncDeviceAttendance as SyncJob;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\Setting;
use App\Models\User;
use App\Services\Hr\ReadZktecoAttendance;
use App\Services\Hr\SyncDeviceAttendance;
use App\Support\AttendanceDeviceSettings;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use HafizAsifAli\ZkTeco\DeviceConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['attendance_device.enabled' => false, 'attendance_device.processing_enabled' => false]);
});

it('configures the device independently from other settings and never hydrates the saved key', function (): void {
    Livewire::test(Settings::class)->callAction(TestAction::make('configureAttendanceDevice')->schemaComponent(true, 'form'), [
        'enabled' => true, 'host' => '100.90.80.70', 'port' => 4371,
        'transport' => 'tcp', 'timeout' => 7, 'timezone' => 'Africa/Addis_Ababa', 'comm_key' => '123456',
    ])->assertHasNoActionErrors()->assertNotified('Device settings saved');

    $settings = Setting::firstOrFail();
    expect(AttendanceDeviceSettings::get('host'))->toBe('100.90.80.70')
        ->and(AttendanceDeviceSettings::get('comm_key'))->toBe('123456')
        ->and($settings->getRawOriginal('attendance_device'))->not->toContain('123456')
        ->and($settings->toArray())->not->toHaveKey('attendance_device')
        ->and(AttendanceDeviceSettings::formState())->not->toHaveKey('comm_key');

    Livewire::test(Settings::class)->mountAction(TestAction::make('configureAttendanceDevice')->schemaComponent(true, 'form'))
        ->assertSet('mountedActions.0.data.host', '100.90.80.70')
        ->assertSet('mountedActions.0.data.comm_key', null);
});

it('preserves a blank key and allows resetting it to zero', function (): void {
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'comm_key' => 1234]);
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'comm_key' => '']);
    expect(AttendanceDeviceSettings::get('comm_key'))->toBe(1234);
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'comm_key' => 0]);
    expect(AttendanceDeviceSettings::get('comm_key'))->toBe(0);
});

it('rejects invalid connection settings without changing the saved configuration', function (array $invalid): void {
    AttendanceDeviceSettings::save(AttendanceDeviceSettings::formState());
    $original = Setting::firstOrFail()->attendance_device;
    expect(fn () => AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), ...$invalid]))->toThrow(ValidationException::class);
    expect(Setting::firstOrFail()->attendance_device)->toBe($original);
})->with([
    [['port' => 65536]], [['host' => 'tcp://localhost/path']], [['transport' => 'http']],
    [['timeout' => 100]], [['timezone' => 'Invalid/Zone']], [['comm_key' => -1]],
]);

it('denies finance device changes and connection tests', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    Livewire::test(Settings::class)->assertActionDoesNotExist(TestAction::make('configureAttendanceDevice')->schemaComponent(true, 'form'))->assertActionDoesNotExist(TestAction::make('testAttendanceDevice')->schemaComponent(true, 'form'));
    expect(fn () => AttendanceDeviceSettings::save(['host' => 'attacker.test']))->toThrow(HttpException::class);
    expect(Setting::firstOrFail()->attendance_device)->toBeNull();
});

it('tests the saved connection without downloading punches or reporting a successful sync', function (): void {
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('testConnection')->once();
    $reader->shouldNotReceive('handle');
    app()->instance(ReadZktecoAttendance::class, $reader);
    Livewire::test(Settings::class)->callAction(TestAction::make('testAttendanceDevice')->schemaComponent(true, 'form'))->assertNotified('Device connected');
    expect(AttendanceDevicePunch::count())->toBe(0)->and(AttendanceDeviceSync::count())->toBe(0);
});

it('shows a useful connection failure without exposing SDK exception details', function (): void {
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('testConnection')->andThrow(new RuntimeException('secret comm key 1234'));
    app()->instance(ReadZktecoAttendance::class, $reader);
    Livewire::test(Settings::class)->callAction(TestAction::make('testAttendanceDevice')->schemaComponent(true, 'form'))->assertNotified('Connection failed');
});

it('reads fresh saved settings in successive queued jobs and respects disablement', function (): void {
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->once()->withArgs(fn (array $settings): bool => $settings['host'] === 'device-one.test')->andReturn([]);
    $reader->shouldReceive('handle')->once()->withArgs(fn (array $settings): bool => $settings['host'] === 'device-two.test')->andReturn([]);
    app()->instance(ReadZktecoAttendance::class, $reader);
    $synchronizer = app(SyncDeviceAttendance::class);
    $job = new SyncJob;
    $id = $job->uniqueId();
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'host' => 'device-one.test', 'enabled' => true]);
    $job->handle($synchronizer);
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'host' => 'device-two.test']);
    $job->handle($synchronizer);
    expect($job->uniqueId())->toBe($id)->and(AttendanceDeviceSync::count())->toBe(1);
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'enabled' => false]);
    $job->handle($synchronizer);
});

it('allows import processing while protecting device identity and queue configuration', function (): void {
    $original = AttendanceDeviceSettings::all();
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'id' => 'different', 'processing_enabled' => true, 'queue_connection' => 'sync']);
    expect(AttendanceDeviceSettings::get('id'))->toBe($original['id'])
        ->and(AttendanceDeviceSettings::get('processing_enabled'))->toBeTrue()
        ->and(AttendanceDeviceSettings::get('queue_connection'))->toBe($original['queue_connection']);
});

it('places the device card in the shared integration grid without a heading', function (): void {
    $component = Livewire::test(Settings::class)->assertSee('ZKTeco');
    $grid = collect($component->instance()->form->getFlatComponents(withHidden: true))
        ->first(fn ($item): bool => $item->getKey(isAbsolute: false) === 'integration-grid');
    expect($grid)->not->toBeNull();
    $card = collect($grid->getChildSchema()->getComponents())
        ->first(fn ($item): bool => $item->getKey(isAbsolute: false) === 'attendance-device-card');
    expect($card)->not->toBeNull()->and($card->getHeading())->toBeNull();
});

it('saves the import toggle through the configuration modal', function (): void {
    Livewire::test(Settings::class)
        ->callAction(TestAction::make('configureAttendanceDevice')->schemaComponent(true, 'form'), [
            ...AttendanceDeviceSettings::formState(), 'processing_enabled' => true,
        ])->assertHasNoActionErrors()->assertNotified();
    expect(AttendanceDeviceSettings::get('processing_enabled'))->toBeTrue();
});

it('switches automatic sync on the card without changing import processing or other settings', function (): void {
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'processing_enabled' => true]);
    Livewire::test(Settings::class)->set('data.attendance_device_enabled', true)->assertHasNoErrors();
    expect(AttendanceDeviceSettings::get('enabled'))->toBeTrue()
        ->and(AttendanceDeviceSettings::get('processing_enabled'))->toBeTrue()
        ->and(Setting::firstOrFail()->getAttributes())->not->toHaveKey('attendance_device_enabled');
});

it('builds the SDK connection from saved settings', function (): void {
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'host' => 'office-device.test', 'port' => 4371, 'comm_key' => 987, 'transport' => 'udp', 'timeout' => 8]);
    $reader = new class extends ReadZktecoAttendance
    {
        public function configuration(): DeviceConfig
        {
            return $this->device()->config;
        }
    };
    $config = $reader->configuration();
    expect($config->host)->toBe('office-device.test')->and($config->port)->toBe(4371)
        ->and($config->commKey)->toBe(987)->and($config->transport)->toBe('udp')->and($config->timeout)->toBe(8.0);
});

it('evaluates scheduled synchronization against the latest saved enabled flag', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => $event->description === 'attendance-device-sync');
    expect($event->filtersPass(app()))->toBeFalse();
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'enabled' => true]);
    expect($event->filtersPass(app()))->toBeTrue();
    AttendanceDeviceSettings::save([...AttendanceDeviceSettings::formState(), 'enabled' => false]);
    expect($event->filtersPass(app()))->toBeFalse();
});
