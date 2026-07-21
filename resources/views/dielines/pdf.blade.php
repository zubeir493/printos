<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
        }
        .title {
            font-size: 10px;
            margin-bottom: 8px;
        }
        .drawing svg {
            height: auto;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="title">{{ $name }}</div>
    <div class="drawing">{!! $svg !!}</div>
</body>
</html>
