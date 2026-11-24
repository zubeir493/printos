<div class="estimator-readiness" aria-live="polite">
    @if ($result->isComplete())
        <div class="estimator-ready-message">All required inputs are complete. Review the cost breakdown, then save and finalize.</div>
    @else
        <p class="estimator-section-title">{{ count($result->diagnostics) }} {{ count($result->diagnostics) === 1 ? 'item needs' : 'items need' }} attention</p>
        <ul class="estimator-issues">
            @foreach (array_slice($result->diagnostics, 0, 6) as $message)<li>{{ $message }} <button type="button" class="ms-2 font-medium text-primary-600 hover:underline dark:text-primary-400" wire:click="reviewEstimatorStep('{{ $this->estimatorIssueTarget($message) }}')">Review <span aria-hidden="true">↗</span></button></li>@endforeach
        </ul>
        @if (count($result->diagnostics) > 6)
            <details class="estimator-detail"><summary>Show {{ count($result->diagnostics) - 6 }} more</summary><ul class="estimator-issues">@foreach (array_slice($result->diagnostics, 6) as $message)<li>{{ $message }} <button type="button" class="ms-2 font-medium text-primary-600 hover:underline dark:text-primary-400" wire:click="reviewEstimatorStep('{{ $this->estimatorIssueTarget($message) }}')">Review ↗</button></li>@endforeach</ul></details>
        @endif
    @endif
</div>
