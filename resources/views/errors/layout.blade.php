<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - PrintOS</title>
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            background-color: #ffffff;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-6 py-12 text-gray-950">
    <div class="w-full max-w-md text-center">
        <p class="text-sm font-medium text-gray-400">Error {{ $code }}</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-normal">{{ $heading }}</h1>
        <p class="mt-3 text-sm leading-6 text-gray-500">{{ $message }}</p>

        <div class="mt-8 flex justify-center">
            <a href="{{ url('/') }}" class="inline-flex items-center rounded-md bg-gray-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800">
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
