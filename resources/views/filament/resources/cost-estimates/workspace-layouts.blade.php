<div class="estimator-layouts">
    @forelse ($result->layouts as $layout)
        <div class="estimator-layout-block">
            <div class="estimator-layout-heading">
                <h3>{{ $layout['label'] }} · sheet layout</h3>
                <p>{{ $layout['machine_name'] }} · {{ $layout['sheet_width_mm'] }} × {{ $layout['sheet_height_mm'] }} mm press sheet · {{ $layout['parent_width_mm'] }} × {{ $layout['parent_height_mm'] }} mm parent</p>
            </div>
            <div class="estimator-layout-grid">
                <div class="estimator-sheet-preview">
                    <svg viewBox="0 0 {{ $layout['sheet_width_mm'] }} {{ $layout['sheet_height_mm'] }}" role="img" aria-label="{{ $layout['ups'] }} copies per press sheet for {{ $layout['label'] }}" preserveAspectRatio="xMidYMid meet">
                        <rect x="0" y="0" width="{{ $layout['sheet_width_mm'] }}" height="{{ $layout['sheet_height_mm'] }}" fill="white" stroke="#94a3b8" stroke-width="1" />
                        <rect x="0" y="0" width="{{ $layout['sheet_width_mm'] }}" height="{{ $layout['top_mm'] }}" fill="#e2e8f0" />
                        @if (isset($layout['placements']))
                            @foreach (array_slice($layout['placements'], 0, 60) as $placement)
                                <g transform="translate({{ $placement['x'] }} {{ $placement['y'] }}) rotate({{ $placement['rotation'] }})">
                                    @foreach (array_slice($layout['source_geometry']['layers']['cut'] ?? [], 0, 80) as $line)
                                        <line x1="{{ $line['x1'] }}" y1="{{ $line['y1'] }}" x2="{{ $line['x2'] }}" y2="{{ $line['y2'] }}" stroke="#6366f1" stroke-width="0.7" />
                                    @endforeach
                                </g>
                            @endforeach
                        @else
                            @for ($i = 0; $i < min(180, $layout['ups']); $i++)
                                <rect x="{{ $layout['left_mm'] + ($i % $layout['across']) * ($layout['cell_width_mm'] + $layout['gap_mm']) }}" y="{{ $layout['top_mm'] + intdiv($i, $layout['across']) * ($layout['cell_height_mm'] + $layout['gap_mm']) }}" width="{{ $layout['cell_width_mm'] }}" height="{{ $layout['cell_height_mm'] }}" fill="#eef2ff" stroke="#818cf8" stroke-width="0.8" />
                            @endfor
                        @endif
                    </svg>
                    <span class="estimator-caption">{{ isset($layout['placements']) ? 'Saved dieline nesting' : 'Rectangular grid · includes bleed and spacing' }}{{ $layout['ups'] > 180 ? ' · preview abbreviated' : '' }}</span>
                </div>
                <dl class="estimator-layout-metrics">
                    @foreach (['ups' => 'Copies / press sheet', 'forms' => 'Distinct forms', 'net_press_sheets' => 'Net press sheets', 'gross_press_sheets' => 'Gross press sheets', 'waste_sheets' => 'Press spoilage sheets', 'parent_sheets' => 'Parent sheets to consume', 'plates' => 'Printing plates', 'passes' => 'Passes / form'] as $key => $label)
                        <div><dt>{{ $label }}</dt><dd>{{ number_format($layout[$key]) }}</dd></div>
                    @endforeach
                    <div><dt>Area utilization</dt><dd>{{ number_format($layout['utilization_percent'], 1) }}%</dd></div>
                </dl>
            </div>
            @if ($layout['signature_pages'] > 0)<p class="estimator-caption">{{ $layout['signature_pages'] }} pages per folded signature. The estimator prices the confirmed layout; it does not produce a paginated prepress file.</p>@endif
        </div>
    @empty
        <div class="estimator-empty">Select stock, a configured machine and flat dimensions to see compatible sheet layouts.</div>
    @endforelse
</div>
