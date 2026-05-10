@include('errors.layout', [
    'code'    => 419,
    'color'   => 'warning',
    'title'   => 'Session Expired',
    'heading' => 'Your session has expired',
    'message' => 'You\'ve been inactive for a while and your session timed out for security reasons. Please go back and try again — your work may still be there.',
    'backUrl' => url()->previous() !== url()->current() ? url()->previous() : null,
])
