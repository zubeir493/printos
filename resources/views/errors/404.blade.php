@include('errors.layout', [
    'code'    => 404,
    'color'   => 'warning',
    'title'   => 'Page Not Found',
    'heading' => 'We couldn\'t find that page',
    'message' => 'The page you\'re looking for doesn\'t exist or may have been moved. Double-check the URL, or use the buttons below to get back on track.',
    'backUrl' => url()->previous() !== url()->current() ? url()->previous() : null,
])
