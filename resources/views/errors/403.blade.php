@include('errors.layout', [
    'code' => 403,
    'color' => 'danger',
    'title' => 'Access Denied',
    'heading' => 'Access denied',
    'message' => 'Your account does not have access to this page.',
])
