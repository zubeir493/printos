<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            size: {{ $widthMm ?? 210 }}mm {{ $heightMm ?? 297 }}mm;
        }
        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
        }
        .drawing {
            height: {{ $heightMm ?? 297 }}mm;
            width: {{ $widthMm ?? 210 }}mm;
        }
        .drawing img {
            display: block;
            height: 100%;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="drawing">
        <img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" alt="Dieline">
    </div>
</body>
</html>
