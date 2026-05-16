<div class="space-y-2 border-t pt-4 mt-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500 text-xs uppercase tracking-wider">Total Amount</div>
            <div class="font-semibold text-gray-900">{{ \App\Support\Money::format($total_amount) }}</div>
        </div>
        
        <div class="bg-green-50 rounded-lg p-3">
            <div class="text-green-600 text-xs uppercase tracking-wider">Total Paid</div>
            <div class="font-semibold text-green-700">{{ \App\Support\Money::format($total_paid) }}</div>
            <div class="text-green-600 text-xs">{{ $payment_count }} payment{{ $payment_count != 1 ? 's' : '' }}</div>
        </div>
        
        <div class="{{ $balance_due > 0 ? 'bg-red-50' : 'bg-blue-50' }} rounded-lg p-3">
            <div class="{{ $balance_due > 0 ? 'text-red-600' : 'text-blue-600' }} text-xs uppercase tracking-wider">
                {{ $balance_due > 0 ? 'Balance Due' : 'Fully Paid' }}
            </div>
            <div class="font-semibold {{ $balance_due > 0 ? 'text-red-700' : 'text-blue-700' }}">
                {{ \App\Support\Money::format($balance_due) }}
            </div>
        </div>
        
        <div class="bg-purple-50 rounded-lg p-3">
            <div class="text-purple-600 text-xs uppercase tracking-wider">Payment Progress</div>
            <div class="font-semibold text-purple-700">
                {{ $total_amount > 0 ? round(($total_paid / $total_amount) * 100, 1) : 0 }}%
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                <div class="bg-purple-600 h-1.5 rounded-full" style="width: {{ $total_amount > 0 ? min(100, ($total_paid / $total_amount) * 100) : 0 }}%"></div>
            </div>
        </div>
    </div>
</div>
