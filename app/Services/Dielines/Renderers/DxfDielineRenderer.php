<?php

namespace App\Services\Dielines\Renderers;

class DxfDielineRenderer
{
    /**
     * @param  array<string, mixed>  $geometry
     */
    public function render(array $geometry): string
    {
        $lines = [
            '0', 'SECTION', '2', 'HEADER', '9', '$INSUNITS', '70', '4', '0', 'ENDSEC',
            '0', 'SECTION', '2', 'TABLES', '0', 'TABLE', '2', 'LAYER',
            ...$this->layer('CUT', 7),
            ...$this->layer('CREASE', 5),
            ...$this->layer('GLUE', 3),
            ...$this->layer('BLEED', 30),
            '0', 'ENDTAB', '0', 'ENDSEC', '0', 'SECTION', '2', 'ENTITIES',
        ];

        foreach (['CUT' => 'cut', 'CREASE' => 'crease', 'BLEED' => 'bleed'] as $dxfLayer => $geometryLayer) {
            foreach ($geometry['layers'][$geometryLayer] ?? [] as $line) {
                array_push($lines, ...$this->line($dxfLayer, $line));
            }
        }

        foreach ($geometry['layers']['glue'] ?? [] as $area) {
            $points = $area['points'];
            $count = count($points);

            for ($index = 0; $index < $count; $index++) {
                array_push($lines, ...$this->line('GLUE', [
                    'x1' => $points[$index]['x'],
                    'y1' => $points[$index]['y'],
                    'x2' => $points[($index + 1) % $count]['x'],
                    'y2' => $points[($index + 1) % $count]['y'],
                ]));
            }
        }

        array_push($lines, '0', 'ENDSEC', '0', 'EOF');

        return implode("\r\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function layer(string $name, int $color): array
    {
        return ['0', 'LAYER', '2', $name, '70', '0', '62', (string) $color, '6', 'CONTINUOUS'];
    }

    /**
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $line
     * @return array<int, string>
     */
    private function line(string $layer, array $line): array
    {
        return [
            '0', 'LINE',
            '8', $layer,
            '10', (string) $line['x1'],
            '20', (string) (-1 * $line['y1']),
            '30', '0',
            '11', (string) $line['x2'],
            '21', (string) (-1 * $line['y2']),
            '31', '0',
        ];
    }
}
