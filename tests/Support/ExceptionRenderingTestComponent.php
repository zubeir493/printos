<?php

namespace Tests\Support;

use League\Flysystem\UnableToWriteFile;
use Livewire\Component;

class ExceptionRenderingTestComponent extends Component
{
    public function fail(): void
    {
        throw new \RuntimeException('Cannot complete this action in its current state.');
    }

    public function missing(): void
    {
        abort(404);
    }

    public function storageOffline(): void
    {
        throw UnableToWriteFile::atLocation(
            'job-order-cost-calculations/All Employee.csv',
            'Error executing "PutObject"; cURL error 6: Could not resolve host: s3.eu-central-003.backblazeb2.com'
        );
    }

    public function render(): string
    {
        return '<div>Exception test</div>';
    }
}
