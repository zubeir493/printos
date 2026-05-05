<?php

use App\Support\PrivateStorage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class);

it('uses configured private disk for signed urls', function () {
    config()->set('filesystems.private_disk', 'b2');

    URL::forceRootUrl('http://printos.test');

    $url = PrivateStorage::downloadUrl('artworks/example.pdf', now()->addMinutes(5));

    expect($url)->toContain('/private-storage/b2/artworks/example.pdf');
});
