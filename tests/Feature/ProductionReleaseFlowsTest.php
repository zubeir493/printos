<?php

use App\Filament\Resources\Artworks\Pages\ListArtworks;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\EmailLogs\Pages\ManageEmailLogs;
use App\Filament\Resources\Proformas\ProformaResource;
use App\Mail\ShareArtwork;
use App\Models\Artwork;
use App\Models\EmailLog;
use App\Models\JobOrder;
use App\Models\Proforma;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('cached configuration preserves trusted proxies without trusting other callers', function (string $remoteAddress, int $expectedStatus): void {
    $cachedConfigPath = tempnam(sys_get_temp_dir(), 'printos-proxy-config-');
    $configuration = config()->all();
    $configuration['app']['env'] = 'production';
    $configuration['trustedproxy']['proxies'] = '10.0.0.1';
    file_put_contents($cachedConfigPath, '<?php return '.var_export($configuration, true).';');

    try {
        $process = new Process([PHP_BINARY, '-r', <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $request = Illuminate\Http\Request::create('http://example.test/proxy-probe', 'GET', server: [
                'REMOTE_ADDR' => $argv[1],
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]);
            $app->instance('request', $request);
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            Illuminate\Support\Facades\Route::get('/proxy-probe', fn () => response()->json([
                'secure' => request()->secure(),
                'cached' => app()->configurationIsCached(),
            ]));
            $response = $kernel->handle($request);
            echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent()]);
            PHP, $remoteAddress], base_path(), [
            'APP_CONFIG_CACHE' => $cachedConfigPath,
            'TRUSTED_PROXIES' => false,
        ]);
        $process->mustRun();

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe($expectedStatus);

        if ($expectedStatus === 200) {
            expect(json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR))
                ->toBe(['secure' => true, 'cached' => true]);
        }
    } finally {
        unlink($cachedConfigPath);
    }
})->with([
    'trusted proxy' => ['10.0.0.1', 200],
    'untrusted caller' => ['10.0.0.2', 302],
]);

test('finance and operations can navigate to and open proforma screens', function (UserRole $role): void {
    $this->actingAs(User::factory()->create(['role' => $role]));
    Filament::setCurrentPanel(Filament::getPanel($role->value));
    $proforma = Proforma::factory()->create(['status' => 'draft']);

    $navigationUrls = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getUrl());

    expect($navigationUrls)->toContain(ProformaResource::getUrl('index'));

    foreach (['index', 'create', 'view', 'edit'] as $page) {
        $this->get(ProformaResource::getUrl($page, in_array($page, ['view', 'edit'], true) ? ['record' => $proforma] : []))
            ->assertSuccessful();
    }
})->with([UserRole::Finance, UserRole::Operations]);

test('email history remains visible but cannot be manually created or changed', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel($role->value));
    $emailLog = EmailLog::create([
        'recipient_email' => 'printer@example.com',
        'subject' => 'Artwork delivery',
        'sent_by' => $user->id,
        'sent_at' => now(),
    ]);

    Livewire::test(ManageEmailLogs::class)
        ->assertCanSeeTableRecords([$emailLog])
        ->assertActionDoesNotExist('create');

    expect(EmailLogResource::canCreate())->toBeFalse()
        ->and(EmailLogResource::canEdit($emailLog))->toBeFalse()
        ->and(EmailLogResource::canDelete($emailLog))->toBeFalse()
        ->and(EmailLogResource::canDeleteAny())->toBeFalse();
})->with([UserRole::Admin, UserRole::Operations, UserRole::Design]);

test('designers can still send artwork and generate a delivery log', function (): void {
    Storage::fake('s3');
    Mail::fake();
    $designer = User::factory()->create(['role' => UserRole::Design]);
    $this->actingAs($designer);
    Filament::setCurrentPanel(Filament::getPanel('design'));
    $jobOrder = JobOrder::factory()->create(['designer_id' => $designer->id, 'status' => 'design']);
    $artwork = Artwork::create([
        'job_order_id' => $jobOrder->id,
        'filename' => 'artworks/cover.pdf',
        'uploaded_by' => $designer->id,
    ]);

    Livewire::test(ListArtworks::class)
        ->callAction(TestAction::make('sendEmail')->table($artwork), [
            'recipient_email' => 'printer@example.com',
            'subject' => 'Cover artwork',
            'message' => 'Please review the cover.',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Email sent successfully');

    Mail::assertSent(ShareArtwork::class, fn (ShareArtwork $mail): bool => $mail->hasTo('printer@example.com') && $mail->artwork->is($artwork));
    $this->assertDatabaseHas('email_logs', [
        'recipient_email' => 'printer@example.com',
        'artwork_id' => $artwork->id,
        'sent_by' => $designer->id,
    ]);
});
