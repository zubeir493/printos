<?php

namespace App\Filament\Resources\Dielines\Pages;

use App\Filament\Resources\Dielines\DielineResource;
use App\Models\Dieline;
use App\Models\JobOrderTask;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Group;

class ListDielines extends ListRecords
{
    protected static string $resource = DielineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createDieline')
                ->label('New Dieline')
                ->icon('heroicon-m-plus')
                ->modalWidth('lg')
                ->modalHeading('New dieline')
                ->schema([
                    Group::make()
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->default('New dieline'),
                            Select::make('template_key')
                                ->label('Dieline type')
                                ->options(fn (): array => app(DielineTemplateRegistry::class)->options())
                                ->default('reverse-tuck-flap-box')
                                ->required()
                                ->searchable(),
                            Select::make('job_order_task_id')
                                ->label('Job task')
                                ->options(fn (): array => JobOrderTask::query()
                                    ->with('jobOrder')
                                    ->latest('id')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn (JobOrderTask $task): array => [
                                        $task->id => "{$task->name} ({$task->jobOrder?->job_order_number})",
                                    ])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $templateKey = (string) $data['template_key'];
                    $dimensions = app(DielineTemplateRegistry::class)->template($templateKey)->defaults();
                    $geometry = app(DielineGeometryService::class)->generate($templateKey, $dimensions);

                    $dieline = Dieline::query()->create([
                        'name' => $data['name'],
                        'template_key' => $templateKey,
                        'job_order_task_id' => $data['job_order_task_id'] ?? null,
                        'created_by' => auth()->id(),
                        'dimensions' => $dimensions,
                        'geometry' => $geometry,
                    ]);

                    $this->redirect(DielineResource::getUrl('edit', ['record' => $dieline]), navigate: true);
                }),
        ];
    }
}
