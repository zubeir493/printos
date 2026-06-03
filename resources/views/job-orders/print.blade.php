<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Order {{ $jobOrderPrint['job_order_number'] }}</title>
    <style>
        body {
            background: #ffffff;
            color: #222222;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
        }

        .page {
            background: #ffffff;
            border: 0;
            margin: 0 auto;
            padding: 32px;
        }

        .header {
            border-bottom: 2px solid #1f2933;
            margin-bottom: 20px;
            padding-bottom: 14px;
        }

        .brand {
            color: #384250;
            float: right;
            font-size: 10px;
            line-height: 1.35;
            text-align: right;
            width: 260px;
        }

        .logo {
            display: block;
            margin: 0 0 8px auto;
            max-height: 54px;
            max-width: 128px;
            object-fit: contain;
        }

        h1 {
            color: #1f2933;
            font-size: 24px;
            margin: 0 0 4px;
        }

        h2 {
            border-bottom: 1px solid #d9dde3;
            color: #1f2933;
            font-size: 15px;
            margin: 20px 0 8px;
            padding-bottom: 4px;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 12px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d9dde3;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #1f2933;
            color: #ffffff;
            font-weight: bold;
        }

        .muted {
            color: #5f6b7a;
        }

        .meta-table td:first-child {
            background: #f7f8fa;
            font-weight: bold;
            width: 28%;
        }

        .task {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .task-title {
            background: #1f2933;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 10px;
        }

        .empty {
            color: #5f6b7a;
            font-style: italic;
        }

        .services-table td {
            border: none;
            padding: 3px 8px 3px 0;
            width: 50%;
        }

        .checkmark {
            color: #1f2933;
            font-weight: bold;
            padding-right: 5px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                @if(! empty($jobOrderPrint['company']['logo_data_uri']) || ! empty($jobOrderPrint['company']['logo']))
                    <img src="{{ $jobOrderPrint['company']['logo_data_uri'] ?? $jobOrderPrint['company']['logo'] }}" class="logo" alt="{{ $jobOrderPrint['company']['name'] ?? $jobOrderPrint['app_name'] }}"><br>
                @endif
            </div>
            <h1>Job Order {{ $jobOrderPrint['job_order_number'] }}</h1>
            <div class="muted">Production printout</div>
        </div>

        <table class="meta-table">
            <tr>
                <td>Created</td>
                <td>{{ $jobOrderPrint['created_at'] ?? 'Not set' }}</td>
                <td>Submission Date</td>
                <td>{{ $jobOrderPrint['submission_date'] ?? 'Not set' }}</td>
            </tr>
            <tr>
                <td>Deadline</td>
                <td>{{ $jobOrderPrint['due_date'] ?? 'Not set' }}</td>
                <td>Client</td>
                <td>{{ $jobOrderPrint['client']['name'] }}</td>
            </tr>
            <tr>
                <td>Client Contact</td>
                <td colspan="3">
                    {{ $jobOrderPrint['client']['phone'] ?? 'No phone' }}
                    @if($jobOrderPrint['client']['email'])
                        <br>{{ $jobOrderPrint['client']['email'] }}
                    @endif
                    @if($jobOrderPrint['client']['address'])
                        <br>{{ $jobOrderPrint['client']['address'] }}
                    @endif
                </td>
            </tr>
        </table>

        <h2>Tasks And Required Materials</h2>
        @forelse($jobOrderPrint['tasks'] as $task)
            <div class="task">
                <div class="task-title">{{ $task['name'] }}</div>
                <table>
                    <tr>
                        <td><strong>Quantity</strong></td>
                        <td>{{ $task['quantity'] ?? 'Not set' }}</td>
                        <td><strong>Size</strong></td>
                        <td>{{ $task['size'] ?? 'Not set' }}</td>
                    </tr>
                    @if($task['instructions'])
                        <tr>
                            <td><strong>Instructions</strong></td>
                            <td colspan="3">{{ $task['instructions'] }}</td>
                        </tr>
                    @endif
                </table>

                @if(count($task['materials']))
                    <table>
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>SKU</th>
                                <th>Required</th>
                                <th>Reserve</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($task['materials'] as $material)
                                <tr>
                                    <td>{{ $material['material_name'] }}</td>
                                    <td>{{ $material['sku'] ?? '-' }}</td>
                                    <td>{{ number_format($material['required_quantity'], 2) }} {{ $material['unit'] }}</td>
                                    <td>{{ $material['reserve_quantity'] === null ? '-' : number_format($material['reserve_quantity'], 2).' '.$material['unit'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="empty">No required materials recorded for this task.</p>
                @endif
            </div>
        @empty
            <p class="empty">No tasks recorded.</p>
        @endforelse

        <h2>Additional Services</h2>
        @if(count($jobOrderPrint['services']))
            <table class="services-table">
                @foreach(array_chunk($jobOrderPrint['services'], 2) as $serviceRow)
                    <tr>
                        @foreach($serviceRow as $service)
                            <td><span class="checkmark">-</span> {{ $service }}</td>
                        @endforeach
                        @if(count($serviceRow) === 1)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <p class="empty">No additional services recorded.</p>
        @endif

        <h2>Remarks</h2>
        <p>{{ $jobOrderPrint['remarks'] ?: 'No remarks recorded.' }}</p>
    </div>
</body>
</html>
