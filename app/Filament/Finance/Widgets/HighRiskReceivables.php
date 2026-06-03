<?php

namespace App\Filament\Finance\Widgets;

use App\Mail\CustomerReminderMail;
use App\Models\EmailLog;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class HighRiskReceivables extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'High-Risk Receivables (Aging > 45 Days)';

    public function table(Table $table): Table
    {
        $paymentsByOrder = Payment::query()
            ->select('payable_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->where('payable_type', SalesOrder::class)
            ->whereNull('voided_at')
            ->groupBy('payable_id');

        $balancesByPartner = SalesOrder::query()
            ->leftJoinSub($paymentsByOrder, 'order_payments', fn ($join) => $join->on('order_payments.payable_id', '=', 'sales_orders.id'))
            ->select('sales_orders.partner_id')
            ->selectRaw('SUM(sales_orders.total - COALESCE(order_payments.paid_amount, 0)) as total_balance')
            ->groupBy('sales_orders.partner_id');

        return $table
            ->query(
                Partner::query()
                    ->where('is_customer', true)
                    ->joinSub($balancesByPartner, 'receivable_balances', fn ($join) => $join->on('receivable_balances.partner_id', '=', 'partners.id'))
                    ->select('partners.*')
                    ->selectRaw('receivable_balances.total_balance')
                    ->where('receivable_balances.total_balance', '>', 0)
                    ->orderByDesc('receivable_balances.total_balance')
                    ->limit(5)
            )
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Contact'),
                Tables\Columns\TextColumn::make('total_balance')
                    ->label('Outstanding Balance')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->color('danger')
                    ->sortable(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('remind')
                        ->label('Send Reminder')
                        ->icon('heroicon-m-envelope')
                        ->color('warning')
                        ->action(function (Partner $record): void {
                            if (blank($record->email)) {
                                Notification::make()
                                    ->title('Reminder not sent')
                                    ->body('This customer does not have an email address on file.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            try {
                                Mail::to($record->email)->queue(new CustomerReminderMail($record));

                                EmailLog::create([
                                    'recipient_email' => $record->email,
                                    'subject' => 'Payment reminder from '.config('app.name'),
                                    'message' => 'High-risk receivables reminder sent to '.$record->name,
                                    'sent_by' => Auth::id(),
                                    'sent_at' => now(),
                                ]);

                                Notification::make()
                                    ->title('Reminder Sent')
                                    ->body('Reminder sent to '.$record->email)
                                    ->success()
                                    ->send();
                            } catch (\Throwable $exception) {
                                Notification::make()
                                    ->title('Reminder Failed')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
