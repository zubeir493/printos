<div class="estimator-readiness" aria-live="polite">
    @if ($result->isComplete())
        <div class="estimator-ready-message">All required inputs are complete. Review the cost breakdown, then save and finalize.</div>
    @elseif ($actionable ?? false)
        <p class="estimator-section-title">Finish your estimate</p>
        <div class="estimator-review-tasks">
            @foreach (collect($result->diagnostics)->groupBy(fn ($message) => $this->estimatorIssueTarget($message)) as $target => $messages)
                <button type="button" wire:click="openEstimatorDetails('{{ $target }}')">
                    <span><strong>{{ ['materials' => 'Paper & layout', 'production' => 'Finishing & consumables', 'pricing' => 'Pricing', 'specifications' => 'Product details'][$target] }}</strong><small>{{ $messages->first() }}</small></span><span class="estimator-review-count">{{ count($messages) }} to review <span aria-hidden="true">↗</span></span>
                </button>
            @endforeach
        </div>
    @else
        <p class="estimator-section-title">{{ count($result->diagnostics) }} {{ count($result->diagnostics) === 1 ? 'item needs' : 'items need' }} attention</p>
        <ul class="estimator-issues">
            @foreach (array_slice($result->diagnostics, 0, 6) as $message)<li>{{ $message }} @if ($actionable ?? false)<button type="button" class="estimator-text-action" wire:click="openEstimatorDetails('{{ $this->estimatorIssueTarget($message) }}')">Review <span aria-hidden="true">↗</span></button>@endif</li>@endforeach
        </ul>
        @if (count($result->diagnostics) > 6)
            <details class="estimator-detail"><summary>Show {{ count($result->diagnostics) - 6 }} more</summary><ul class="estimator-issues">@foreach (array_slice($result->diagnostics, 6) as $message)<li>{{ $message }} @if ($actionable ?? false)<button type="button" class="estimator-text-action" wire:click="openEstimatorDetails('{{ $this->estimatorIssueTarget($message) }}')">Review ↗</button>@endif</li>@endforeach</ul></details>
        @endif
    @endif
</div>
