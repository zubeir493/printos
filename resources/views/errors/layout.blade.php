<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — PrintOS</title>
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg">

        {{-- Logo --}}
        <div class="flex justify-center mb-10">
            <img src="{{ asset('images/logo.svg') }}" alt="PrintOS" class="h-8" onerror="this.style.display='none'">
        </div>

        {{-- Callout card --}}
        <div class="rounded-xl border p-6 flex gap-4
            {{ $color === 'danger'  ? 'bg-red-50 border-red-200'    : '' }}
            {{ $color === 'warning' ? 'bg-amber-50 border-amber-200' : '' }}
            {{ $color === 'info'    ? 'bg-blue-50 border-blue-200'   : '' }}
            {{ $color === 'gray'    ? 'bg-white border-gray-200'     : '' }}
        ">
            {{-- Icon --}}
            <div class="shrink-0 mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                    class="w-6 h-6
                        {{ $color === 'danger'  ? 'text-red-500'    : '' }}
                        {{ $color === 'warning' ? 'text-amber-500'  : '' }}
                        {{ $color === 'info'    ? 'text-blue-500'   : '' }}
                        {{ $color === 'gray'    ? 'text-gray-400'   : '' }}
                    ">
                    @if ($color === 'danger')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    @elseif ($color === 'warning')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.051 3.378c.866-1.5 3.032-1.5 3.898 0L21.303 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    @elseif ($color === 'info')
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                    @endif
                </svg>
            </div>

            {{-- Text --}}
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold
                    {{ $color === 'danger'  ? 'text-red-800'    : '' }}
                    {{ $color === 'warning' ? 'text-amber-800'  : '' }}
                    {{ $color === 'info'    ? 'text-blue-800'   : '' }}
                    {{ $color === 'gray'    ? 'text-gray-800'   : '' }}
                ">{{ $heading }}</p>
                <p class="mt-1 text-sm
                    {{ $color === 'danger'  ? 'text-red-700'    : '' }}
                    {{ $color === 'warning' ? 'text-amber-700'  : '' }}
                    {{ $color === 'info'    ? 'text-blue-700'   : '' }}
                    {{ $color === 'gray'    ? 'text-gray-600'   : '' }}
                ">{{ $message }}</p>

                {{-- Back button --}}
                <div class="mt-4 flex gap-3">
                    @if (isset($backUrl))
                        <a href="{{ $backUrl }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold ring-1 ring-inset transition
                                {{ $color === 'danger'  ? 'bg-red-100 text-red-800 ring-red-300 hover:bg-red-200'       : '' }}
                                {{ $color === 'warning' ? 'bg-amber-100 text-amber-800 ring-amber-300 hover:bg-amber-200' : '' }}
                                {{ $color === 'info'    ? 'bg-blue-100 text-blue-800 ring-blue-300 hover:bg-blue-200'    : '' }}
                                {{ $color === 'gray'    ? 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'        : '' }}
                            ">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                                <path fill-rule="evenodd" d="M14 8a.75.75 0 0 1-.75.75H4.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 0 1 1.06 1.06L4.56 7.25h8.69A.75.75 0 0 1 14 8Z" clip-rule="evenodd" />
                            </svg>
                            Go back
                        </a>
                    @endif
                    <a href="{{ url('/') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                            <path d="M8.543 2.232a.75.75 0 0 0-1.085 0l-5.25 5.5A.75.75 0 0 0 2.75 9H4v4a1 1 0 0 0 1 1h1.5a.5.5 0 0 0 .5-.5v-3h2v3a.5.5 0 0 0 .5.5H11a1 1 0 0 0 1-1V9h1.25a.75.75 0 0 0 .543-1.268l-5.25-5.5Z" />
                        </svg>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>

        {{-- Error code --}}
        <p class="mt-6 text-center text-xs text-gray-400">Error {{ $code }}</p>
    </div>
</body>
</html>
