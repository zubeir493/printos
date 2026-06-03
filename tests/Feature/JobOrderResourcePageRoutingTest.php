<?php

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\EditJobOrder;

it('uses dedicated pages for creating and editing job orders', function (): void {
    expect(JobOrderResource::getPages())
        ->toHaveKey('create')
        ->toHaveKey('edit')
        ->and(JobOrderResource::getUrl('create'))->toContain('/job-orders/create')
        ->and(CreateJobOrder::class)->toBeString()
        ->and(EditJobOrder::class)->toBeString();
});
