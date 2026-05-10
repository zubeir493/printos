@include('errors.layout', [
    'code'    => 403,
    'color'   => 'danger',
    'title'   => 'Access Denied',
    'heading' => 'You don\'t have permission to view this',
    'message' => 'Your account doesn\'t have access to this section. If you think this is a mistake, please contact your administrator.',
    'backUrl' => url()->previous() !== url()->current() ? url()->previous() : null,
])
