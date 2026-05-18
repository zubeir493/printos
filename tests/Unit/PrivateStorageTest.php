<?php

use App\Support\PrivateStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class);

it('uses configured private disk for signed urls', function () {
    config()->set('filesystems.private_disk', 'b2');

    URL::forceRootUrl('http://printos.test');

    $url = PrivateStorage::downloadUrl('artworks/example.pdf', now()->addMinutes(5));

    expect($url)->toContain('/private-storage/b2/artworks/example.pdf');
});

it('streams files with non ascii download names using an ascii fallback', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('artworks/example.pdf', 'PDF contents');

    $url = URL::temporarySignedRoute('private-storage.show', now()->addMinutes(5), [
        'disk' => 's3',
        'path' => 'artworks/example.pdf',
        'disposition' => 'attachment',
        'name' => 'عنوان.pdf',
    ]);

    $response = $this->get($url)
        ->assertSuccessful();

    $contentDisposition = $response->headers->get('Content-Disposition');

    expect($contentDisposition)
        ->toContain('attachment; filename=')
        ->toContain("filename*=utf-8''%D8%B9%D9%86%D9%88%D8%A7%D9%86.pdf")
        ->and(str($contentDisposition)->between('filename=', ';')->toString())
        ->toMatch('/^[\x20-\x7E]+$/');
});
