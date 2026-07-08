<?php

test('date based resource tables use the shared date range filter', function (string $path, string $filterCall): void {
    expect(file_get_contents(base_path($path)))->toContain($filterCall);
})->with([
    ['app/Filament/Resources/AttendanceSegments/AttendanceSegmentResource.php', "DateRangeFilter::make('date_range', 'date', 'Date')"],
    ['app/Filament/Resources/Payments/Tables/PaymentsTable.php', "DateRangeFilter::make('payment_date_range', 'payment_date', 'Payment date')"],
    ['app/Filament/Resources/Proformas/Tables/ProformasTable.php', "DateRangeFilter::make('issue_date_range', 'issue_date', 'Issue date')"],
    ['app/Filament/Resources/JournalEntries/Tables/JournalEntriesTable.php', "DateRangeFilter::make('entry_date_range', 'entry_date', 'Entry date')"],
    ['app/Filament/Resources/BankTransactions/Tables/BankTransactionsTable.php', "DateRangeFilter::make('transaction_date_range', 'transaction_date', 'Transaction date')"],
    ['app/Filament/Resources/BankTransfers/Tables/BankTransfersTable.php', "DateRangeFilter::make('transfer_date_range', 'transfer_date', 'Transfer date')"],
    ['app/Filament/Resources/JobOrders/Tables/JobOrdersTable.php', "DateRangeFilter::make('submission_date_range', 'submission_date', 'Submission date')"],
    ['app/Filament/Resources/JobOrderTasks/Tables/JobOrderTasksTable.php', "DateRangeFilter::makeForRelation('deadline_date_range', 'jobOrder', 'submission_date', 'Deadline')"],
    ['app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php', "DateRangeFilter::make('order_date_range', 'order_date', 'Order date')"],
    ['app/Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php', "DateRangeFilter::make('order_date_range', 'order_date', 'Order date')"],
    ['app/Filament/Resources/PayrollRuns/PayrollRunResource.php', "DateRangeFilter::make('period', 'period_start', 'Period', 'period_end')"],
    ['app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php', "DateRangeFilter::make('movement_date_range', 'movement_date', 'Movement date')"],
    ['app/Filament/Resources/StockTransfers/Tables/StockTransfersTable.php', "DateRangeFilter::make('transfer_date_range', 'transfer_date', 'Transfer date')"],
    ['app/Filament/Resources/LeaveRequests/LeaveRequestResource.php', "DateRangeFilter::make('leave_date_range', 'start_date', 'Leave dates', 'end_date')"],
]);

test('legacy date picker table filters were removed', function (): void {
    expect(file_get_contents(base_path('app/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php')))
        ->toContain("DateRangeFilter::make('date_range', 'created_at', 'Date range')")
        ->not->toContain("Filter::make('date_range')");
});
