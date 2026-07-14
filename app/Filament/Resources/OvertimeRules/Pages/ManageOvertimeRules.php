<?php

namespace App\Filament\Resources\OvertimeRules\Pages;

use App\Filament\Resources\OvertimeRules\OvertimeRuleResource;
use App\Models\OvertimeRule;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageOvertimeRules extends ManageRecords
{
    protected static string $resource = OvertimeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => $this->normalizeRuleData($data)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRuleData(array $data): array
    {
        $data = OvertimeRuleResource::normalizeFormData($data);
        $baseCode = filled($data['code'] ?? null)
            ? Str::slug((string) $data['code'], '_')
            : Str::slug((string) $data['name'], '_');
        $code = $baseCode;
        $suffix = 2;

        while (OvertimeRule::query()->where('code', $code)->exists()) {
            $code = $baseCode.'_'.$suffix;
            $suffix++;
        }

        $data['code'] = $code;

        return $data;
    }
}
