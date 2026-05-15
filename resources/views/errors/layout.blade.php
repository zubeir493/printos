<!DOCTYPE html>
<html lang="en" class="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - PrintOS</title>
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: var(--default-font-family);
            background-color: #ffffff;
        }
        .fi-ac-btn-action.fi-color-primary {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            padding: 10px 24px;
            gap: 3.75px;
            font-size: .8rem;
            font-weight: 600;
            text-transform: capitalize;
            background: linear-gradient(180deg, #3E3D3E 0%, #403E40 79.91%, #5E5D5E 100%);
            box-shadow: inset 0px -2px 2px 0.5px #000000, inset 0px 0px 2px 1.5px rgba(255, 255, 255, 0.5);
            border-radius: .5rem;
            flex: none;
            order: 2;
            flex-grow: 0;
            color: white
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-6 py-12 text-gray-950">
    <div class="w-full max-w-md text-center">
        <p style="font-size: 4rem; font-weight: 700; color: #1e1e1e;">{{ $code }}</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-normal">{{ $heading }}</h1>
        <p class="mt-3 text-sm leading-6 text-gray-500">{{ $message }}</p>

        <div class="mt-8 flex justify-center">
            <a href="{{ url('/') }}" class="fi-ac-btn-action fi-color-primary">
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
