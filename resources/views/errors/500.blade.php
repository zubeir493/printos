@include('errors.layout', [
    'code'    => 500,
    'color'   => 'danger',
    'title'   => 'Something Went Wrong',
    'heading' => 'Something went wrong on our end',
    'message' => 'An unexpected error occurred. This has been logged and we\'ll look into it. Please try again — if the problem keeps happening, contact your system administrator.',
    'backUrl' => url()->previous() !== url()->current() ? url()->previous() : null,
])
