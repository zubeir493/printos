<?php

return [
    'paper_sizes' => [
        'a0' => ['name' => 'A0', 'width' => 841, 'height' => 1189],
        'a1' => ['name' => 'A1', 'width' => 594, 'height' => 841],
        'a2' => ['name' => 'A2', 'width' => 420, 'height' => 594],
        'a3' => ['name' => 'A3', 'width' => 297, 'height' => 420],
        'a4' => ['name' => 'A4', 'width' => 210, 'height' => 297],
        'b0' => ['name' => 'B0', 'width' => 1000, 'height' => 1414],
        'b1' => ['name' => 'B1', 'width' => 707, 'height' => 1000],
        'b2' => ['name' => 'B2', 'width' => 500, 'height' => 707],
        'b3' => ['name' => 'B3', 'width' => 353, 'height' => 500],
        'sra1' => ['name' => 'SRA1', 'width' => 640, 'height' => 900],
        'sra2' => ['name' => 'SRA2', 'width' => 450, 'height' => 640],
        'sra3' => ['name' => 'SRA3', 'width' => 320, 'height' => 450],
    ],
    'imposition' => [
        'disk' => env('DIELINE_IMPOSITION_DISK', 'local'),
        'margin' => 0,
        'gap' => 0,
        'search_ms' => 5000,
        'preview_search_ms' => 1200,
        'max_copies' => 500,
        'node_binary' => env('DIELINE_NODE_BINARY', 'node'),
    ],
];
