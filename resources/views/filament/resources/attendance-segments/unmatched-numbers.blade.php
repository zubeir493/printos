<div class="max-h-96 overflow-y-auto">
    <table class="w-full text-left text-sm">
        <thead class="text-gray-500">
            <tr class="border-b border-gray-200">
                <th class="py-2 font-medium">AC / FP No.</th>
                <th class="py-2 text-right font-medium">Punches</th>
                <th class="py-2 text-right font-medium">Last punch</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-950">
            @forelse ($rows as $row)
                <tr>
                    <td class="py-2 font-medium">{{ $row->device_user_id }}</td>
                    <td class="py-2 text-right tabular-nums">{{ number_format($row->punch_count) }}</td>
                    <td class="py-2 text-right tabular-nums">{{ \Carbon\CarbonImmutable::parse($row->last_punch)->format('d M Y, H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-6 text-center text-gray-500">All FP numbers are matched.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
