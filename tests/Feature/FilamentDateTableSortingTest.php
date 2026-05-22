<?php

test('date based filament tables default to latest records first', function (string $path, string $column) {
    $contents = file_get_contents(base_path($path));

    expect($contents)->toContain("->defaultSort('{$column}', 'desc')");
})->with([
    ['app/Filament/Design/Widgets/UrgentProductionQueue.php', 'submission_date'],
    ['app/Filament/Hr/Widgets/SalaryRevisionHistoryTable.php', 'effective_date'],
    ['app/Filament/Operations/Widgets/HighPriorityJobsTable.php', 'submission_date'],
    ['app/Filament/Finance/Widgets/OverdueInvoicesTable.php', 'due_date'],
    ['app/Filament/Widgets/AdminExceptionsTable.php', 'due_date'],
    ['app/Filament/Sales/Widgets/SalesOrdersFocusTable.php', 'due_date'],
    ['app/Filament/Resources/Invoices/Tables/InvoicesTable.php', 'due_date'],
    ['app/Filament/Resources/Dispatches/Tables/DispatchesTable.php', 'delivery_date'],
    ['app/Filament/Resources/Employees/Tables/EmployeesTable.php', 'hire_date'],
    ['app/Filament/Resources/BankTransfers/Tables/BankTransfersTable.php', 'transfer_date'],
    ['app/Filament/Finance/Pages/GeneralLedgerReport.php', 'entry_date'],
    ['app/Filament/Finance/Pages/AccountStatementReport.php', 'entry_date'],
    ['app/Filament/Resources/JobOrderTasks/Tables/JobOrderTasksTable.php', 'jobOrder.submission_date'],
    ['app/Filament/Resources/ProductionPlans/Tables/ProductionPlansTable.php', 'due_date'],
    ['app/Filament/Resources/Payments/Tables/PaymentsTable.php', 'payment_date'],
    ['app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php', 'order_date'],
    ['app/Filament/Resources/Partners/RelationManagers/JobOrdersRelationManager.php', 'submission_date'],
    ['app/Filament/Resources/JobOrders/Tables/JobOrdersTable.php', 'submission_date'],
    ['app/Filament/Resources/SalesOrders/RelationManagers/PaymentsRelationManager.php', 'payment_date'],
    ['app/Filament/Resources/JobOrders/RelationManagers/PaymentsRelationManager.php', 'payment_date'],
    ['app/Filament/Resources/StockAdjustments/Tables/StockAdjustmentsTable.php', 'adjustment_date'],
    ['app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php', 'movement_date'],
    ['app/Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php', 'order_date'],
    ['app/Filament/Resources/PurchaseOrders/RelationManagers/GoodsReceiptsRelationManager.php', 'receipt_date'],
    ['app/Filament/Resources/PurchaseOrders/RelationManagers/PaymentsRelationManager.php', 'payment_date'],
    ['app/Filament/Resources/StockTransfers/Tables/StockTransfersTable.php', 'transfer_date'],
]);
