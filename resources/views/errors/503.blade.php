@include('errors.layout', [
    'code' => 503,
    'color' => 'info',
    'title' => 'Down for Maintenance',
    'heading' => 'Packledge is temporarily unavailable',
    'message' => isset($exception) && $exception->getMessage()
        ? $exception->getMessage()
        : 'Scheduled maintenance is in progress. Please try again shortly.',
])
