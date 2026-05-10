@include('errors.layout', [
    'code'    => 503,
    'color'   => 'info',
    'title'   => 'Down for Maintenance',
    'heading' => 'PrintOS is temporarily unavailable',
    'message' => isset($exception) && $exception->getMessage()
        ? $exception->getMessage()
        : 'We\'re performing scheduled maintenance and will be back shortly. Thank you for your patience.',
])
