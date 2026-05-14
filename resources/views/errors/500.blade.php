@include('errors.layout', [
    'code' => 500,
    'color' => 'danger',
    'title' => 'Something Went Wrong',
    'heading' => 'Something went wrong',
    'message' => 'An unexpected error occurred. This has been logged for review.',
])
