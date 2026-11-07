<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPrecisionGeometry;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\DielinePath;

class FriesScoopTemplate implements DielineTemplateContract
{
    use BuildsPrecisionGeometry;

    /**
     * Local seam profile measured from the public reference preview. Panel and
     * crease positions are calculated separately from the carton dimensions.
     *
     * @var array{free_x: float, top_y: float, bottom_y: float, shoulder_y: float}
     */
    private const SEAM_PROFILE = [
        'free_x' => -0.27153820772,
        'top_y' => 0.253856497447348,
        'bottom_y' => -0.5850719836825,
        'shoulder_y' => -0.07593784421,
    ];

    public function key(): string
    {
        return 'fries-scoop';
    }

    public function name(): string
    {
        return 'Fries Scoop';
    }

    public function description(): string
    {
        return 'A tapered scoop with circular mouth profiles, two scored side gussets, and a curved locking base.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/52210/food-boxes-becf-12013';
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Top width', 'w' => 'Opening depth', 'h' => 'Body height'];
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_5221020260912-2-atsg5l.svg';
    }

    public function defaults(): array
    {
        return ['l' => 100, 'w' => 40, 'h' => 110, 'base_length' => 55, 'camber' => 15, 'board_thickness' => 0.5, 'gusset_allowance' => 1.345, 'front_clearance' => 5];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'base_length', 'label' => 'Base width', 'default' => 50, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'camber', 'label' => 'Mouth camber', 'default' => 15, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 0.5, 'min' => 0.1, 'suffix' => 'mm'],
            ['key' => 'gusset_allowance', 'label' => 'Gusset allowance', 'default' => 1.345, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'front_clearance', 'label' => 'Front rim clearance', 'default' => 5, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $values = $this->dimensionsForRules($dimensions);
        $slope = max(0, ($values['l'] - $values['base_length']) / (2 * max(1, $values['h'])));
        $lead = $values['board_thickness'] / ($slope + tan(deg2rad(10)));

        return [
            'l' => ['max:'.($values['base_length'] + 2 * $values['h'])],
            'w' => ['max:'.$values['base_length']],
            'base_length' => ['max:'.max(0, (float) $values['l'] - 0.1)],
            'camber' => ['max:'.min((float) $values['l'] / 2, (float) $values['h'] / 2)],
            'front_clearance' => ['max:'.max(0, min($values['h'] * 0.8, $values['h'] - $lead - 0.1))],
            'board_thickness' => ['max:'.min($values['base_length'] / 10, $values['h'] / 10)],
            'gusset_allowance' => ['max:'.($values['w'] / 5)],
        ];
    }

    public function generate(array $dimensions): array
    {
        $values = [...$this->defaults(), ...$dimensions];
        $topWidth = $this->dimension($values, 'l', 100);
        $baseWidth = min($this->dimension($values, 'base_length', 50), $topWidth);
        $depth = $this->dimension($values, 'w', 40);
        $height = max(1, $this->dimension($values, 'h', 100));
        $camber = min($this->dimension($values, 'camber', 15), $topWidth / 2, $height / 2);
        $board = min($this->dimension($values, 'board_thickness', 0.5), $baseWidth / 10);
        $frontClearance = min($this->dimension($values, 'front_clearance', 5), $height * 0.8);
        $projection = $depth + $this->dimension($values, 'gusset_allowance', 1.345);
        $slope = ($topWidth - $baseWidth) / (2 * $height);
        $normal = hypot(1, $slope);
        $rootY = -$depth / 2;
        $topY = $rootY - $height;
        $rootX = -$baseWidth / 2;
        $topX = -$topWidth / 2;
        $returnLength = min(2, $depth / 10, $height / 10);
        $returnX = $returnLength * $slope / $normal;
        $returnY = $returnLength / $normal;
        $outerTopX = $topX - $projection;
        $outerTopY = $topY + $slope * $projection;
        $outerRootX = $rootX - $projection;
        $outerRootY = $rootY + $slope * $projection;
        $middleX = $rootX - $projection / 2 + $returnX;
        $middleY = $rootY + $slope * $projection / 2 + $returnY;
        $baseFlare = tan(deg2rad(10));
        $bridgeX = $rootX - $depth / 2 * $baseFlare;
        $intersectionSum = $middleY + $slope * $middleX;
        $reliefX = ($rootX + $baseFlare * $rootY - $baseFlare * $intersectionSum) / (1 - $baseFlare * $slope);
        $reliefY = $intersectionSum - $slope * $reliefX;
        $freeX = $outerTopX + $depth * self::SEAM_PROFILE['free_x'];
        $freeTopY = $outerTopY + $depth * self::SEAM_PROFILE['top_y'];
        $freeBottomY = $outerRootY + $depth * self::SEAM_PROFILE['bottom_y'];
        $shoulderY = $outerRootY + $depth * self::SEAM_PROFILE['shoulder_y'];
        $radius = min(3, $depth * 0.075, $height / 20);

        $sideCut = $this->drawPath(0, 0, 'top', fn (DielinePath $path): DielinePath => $path
            ->moveTo($topX, $topY)
            ->lineTo($outerTopX, $outerTopY)
            ->arcTo($freeX, $freeTopY, $freeX, $freeBottomY, $radius)
            ->lineTo($freeX, $freeBottomY)
            ->lineTo($outerTopX, $shoulderY)
            ->lineTo($outerRootX, $outerRootY)
            ->lineTo($outerRootX + $returnX, $outerRootY + $returnY)
            ->lineTo($middleX, $middleY)
            ->lineTo($reliefX, $reliefY));
        $leftCrease = [
            $this->line($topX, $topY, $rootX, $rootY),
            $this->line($topX - $projection / 2, $topY + $slope * $projection / 2, $middleX, $middleY),
            $this->line($outerTopX, $outerTopY, $outerRootX + $returnX, $outerRootY + $returnY),
        ];
        $rightCrease = $this->reflectLines($leftCrease);
        $rightCrease[2]['x2'] = -$outerRootX;
        $rightCrease[2]['y2'] = $outerRootY;
        $frontRootY = $depth / 2;
        $frontY = $frontRootY + $height - $frontClearance;
        $frontHalf = $baseWidth / 2 + $slope * ($height - $frontClearance) - $board;
        $leadLength = $board / max(0.000001, $slope + $baseFlare);
        $frontLeadX = -$baseWidth / 2 + $baseFlare * $leadLength;
        $frontLeadY = $frontRootY + $leadLength;
        $leftCut = [
            ...$sideCut,
            ...$this->precisionPolyline([[$rootX, $rootY], [$bridgeX, 0], [$rootX, $frontRootY], [$frontLeadX, $frontLeadY], [-$frontHalf, $frontY]]),
        ];
        $bottomRadius = $baseWidth;
        $bottomCenter = sqrt(max(0, $bottomRadius ** 2 - ($baseWidth / 2) ** 2));
        $baseSagitta = $bottomRadius - $bottomCenter;
        $mouthRadius = $camber > 0 ? ($topWidth ** 2 / (8 * $camber)) + $camber / 2 : INF;
        $frontCamber = is_finite($mouthRadius) ? $mouthRadius - sqrt(max(0, $mouthRadius ** 2 - $frontHalf ** 2)) : 0;
        $notchHalf = min($baseWidth * 0.15, $depth / 3);
        $frontFoldCenterY = $frontRootY - $bottomCenter;
        $notchY = $frontFoldCenterY + sqrt(max(0, $bottomRadius ** 2 - $notchHalf ** 2));
        $notchTop = $frontRootY + $baseSagitta - min(12, $depth * 0.3);
        $notchCornerX = $notchHalf - ($notchY - $notchTop) * tan(deg2rad(12));
        $notchRadius = min(2, $notchCornerX / 2);
        $notch = $this->drawPath(0, 0, 'top', fn (DielinePath $path): DielinePath => $path
            ->moveTo(-$notchHalf, $notchY)
            ->arcTo(-$notchCornerX, $notchTop, $notchCornerX, $notchTop, $notchRadius)
            ->arcTo($notchCornerX, $notchTop, $notchHalf, $notchY, $notchRadius)
            ->lineTo($notchHalf, $notchY));
        $leftFold = $this->precisionArc(
            0, $frontFoldCenterY, $bottomRadius,
            atan2($frontRootY - $frontFoldCenterY, -$baseWidth / 2),
            atan2($notchY - $frontFoldCenterY, -$notchHalf),
        );

        return $this->panelGeometry([[
            'cut' => [
                ...$leftCut, ...$this->reflectLines($leftCut),
                ...$this->bowedLine($topX, -$topX, $topY, $camber),
                ...$this->bowedLine(-$frontHalf, $frontHalf, $frontY, $frontCamber),
                ...$notch,
            ],
            'crease' => [
                ...$leftCrease, ...$rightCrease,
                $this->line($bridgeX, 0, -$bridgeX, 0),
                ...$this->bowedLine(-$baseWidth / 2, $baseWidth / 2, $rootY, -$baseSagitta),
                ...$leftFold, ...$this->reflectLines($leftFold),
            ],
        ]], [
            $this->label(0, $topY + $height / 2, 'Back'),
            $this->label(0, $frontRootY + ($height - $frontClearance) / 2, 'Front'),
        ]);
    }
}
