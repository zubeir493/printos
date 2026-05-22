<?php

use App\Filament\Support\TableBadgeFormatter;
use App\UserRole;

test('table badge formatter title cases underscored states', function () {
    expect(TableBadgeFormatter::format('pending_approval'))->toBe('Pending Approval')
        ->and(TableBadgeFormatter::format('bank_transfer'))->toBe('Bank Transfer')
        ->and(TableBadgeFormatter::format(UserRole::Production))->toBe('Production');
});
