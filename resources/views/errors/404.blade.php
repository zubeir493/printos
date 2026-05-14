@include('errors.layout', [
    'code' => 404,
    'color' => 'warning',
    'title' => 'Page Not Found',
    'heading' => 'We could not find that page',
    'message' => 'The page you are looking for does not exist or may have been moved.',
])
