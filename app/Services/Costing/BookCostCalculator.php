<?php

namespace App\Services\Costing;

use App\Models\Setting;
use App\Services\Costing\Concerns\BuildsCostingResults;

class BookCostCalculator implements CostingCalculator
{
    use BuildsCostingResults;

    public function calculate(array $data): CostingResult
    {
        $settings = Setting::getSettings();
        $services = $data['services'] ?? [];
        $book = $services['book'] ?? [];
        $material = $services['material'] ?? [];
        $production = $services['production'] ?? [];
        $commercial = $services['commercial'] ?? [];
        $defaults = $settings->costing_defaults ?? [];

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $pages = max(1, (int) ($book['page_count'] ?? 64));
        $size = (string) ($book['size'] ?? 'A5');
        $binding = (string) ($book['binding'] ?? 'Perfect');
        $coverLaminated = $this->truthy($book['cover_laminated'] ?? true);
        $insidePrinting = $this->truthy($book['inside_printing'] ?? true);
        $textColors = max(1, (int) ($book['text_colors'] ?? 1));
        $coverColors = max(0, (int) ($book['cover_colors'] ?? 4));
        $textAllowance = max(0, (float) ($book['text_allowance_per_signature'] ?? 50));
        $coverAllowance = max(0, (float) ($book['cover_allowance'] ?? 20));
        $textCoverage = max(0, (float) ($book['text_ink_coverage'] ?? 1));
        $coverCoverage = max(0, (float) ($book['cover_ink_coverage'] ?? 1));
        $coverPaperFormat = (string) ($book['cover_paper_format'] ?? 'A1');

        $textPaperSheets = $this->ceil($pages * $quantity / $this->textSheetsPerParent($size) + ($pages / $this->textAllowanceDivisor($size)) * $textAllowance);
        $textPaperPurchaseQuantity = $textPaperSheets / 500;
        $coverPaperSheets = $this->ceil($this->coverPaperSheets($quantity, $pages, $size, $coverPaperFormat) + $coverAllowance);
        $coverPaperPurchaseQuantity = $coverPaperSheets / 100;
        $casePaperQuantity = $this->isHardCover($binding) ? $this->casePaperSheets($quantity, $size) + $coverAllowance : 0;
        $greyBoardQuantity = $this->isHardCover($binding) ? $this->ceil($this->greyBoardSheets($quantity, $size) * 1.02) : 0;
        $endsheetQuantity = $this->isHardCover($binding) ? $this->ceil($this->endsheetSheets($quantity, $size) * 1.05) : 0;
        $textPlates = $this->textPlates($pages, $size) * $textColors;
        $coverPlates = $coverColors;
        $inkQuantity = $this->inkKg($size, $pages, $quantity, $textColors, $coverColors, $textCoverage, $coverCoverage) / 2.5;
        $laminationQuantity = $coverLaminated ? $this->laminationSquareMeters($binding, $size, $pages, $quantity) : 0;
        $wireQuantity = $this->isSaddle($binding) ? ($quantity * 0.2 / 1000) / 2 : 0;
        $packingQuantity = $quantity / 1200;
        $textPlateUnitCost = (float) ($material['text_plate_unit_cost'] ?? $material['plate_unit_cost'] ?? $defaults['book_text_plate_unit_cost'] ?? 0);
        $coverPlateUnitCost = (float) ($material['cover_plate_unit_cost'] ?? $material['plate_unit_cost'] ?? $defaults['book_cover_plate_unit_cost'] ?? $defaults['book_plate_unit_cost'] ?? $defaults['plate_unit_cost'] ?? 900);

        $materialLines = [
            $this->line('Material', 'Text paper', $textPaperPurchaseQuantity, 'ream', (float) ($material['text_paper_unit_cost'] ?? $defaults['book_text_paper_unit_cost'] ?? (7000 / 1.15)), null, ['sheets' => $textPaperSheets]),
            $this->line('Material', 'Cover paper', $this->isHardCover($binding) ? 0 : $coverPaperPurchaseQuantity, 'pack', (float) ($material['cover_paper_unit_cost'] ?? $defaults['book_cover_paper_unit_cost'] ?? 0), null, ['sheets' => $this->isHardCover($binding) ? 0 : $coverPaperSheets]),
            $this->line('Material', 'Case paper', $casePaperQuantity, 'sheet', (float) ($material['case_paper_unit_cost'] ?? $defaults['book_case_paper_unit_cost'] ?? 0)),
            $this->line('Material', 'Grey board', $greyBoardQuantity, 'sheet', (float) ($material['grey_board_unit_cost'] ?? $defaults['book_grey_board_unit_cost'] ?? 0)),
            $this->line('Material', 'Endsheet', $endsheetQuantity, 'sheet', (float) ($material['endsheet_unit_cost'] ?? $defaults['book_endsheet_unit_cost'] ?? 0)),
            $this->line('Plate', 'Text plates', $textPlates, 'plate', $textPlateUnitCost),
            $this->line('Plate', 'Cover plates', $coverPlates, 'plate', $coverPlateUnitCost),
            $this->line('Ink', 'Ink', $inkQuantity, 'kg', (float) ($material['ink_unit_cost'] ?? $defaults['book_ink_unit_cost'] ?? 3000)),
            $this->line('Finishing', 'Lamination film', $laminationQuantity, 'm2', (float) ($material['lamination_unit_cost'] ?? $defaults['book_lamination_unit_cost'] ?? 0)),
            $this->line('Material', 'Wire', $wireQuantity, 'kg', (float) ($material['wire_unit_cost'] ?? $defaults['book_wire_unit_cost'] ?? 160)),
            $this->line('Material', 'Hotmelt glue', 1, 'unit', (float) ($material['hotmelt_glue_unit_cost'] ?? $defaults['book_hotmelt_glue_unit_cost'] ?? 10000)),
            $this->line('Material', 'White glue', 0, 'unit', (float) ($material['white_glue_unit_cost'] ?? $defaults['book_white_glue_unit_cost'] ?? 0)),
            $this->line('Packing', 'Packing material', $packingQuantity, 'unit', (float) ($material['packing_unit_cost'] ?? $defaults['book_packing_material_unit_cost'] ?? 30)),
        ];

        $printingSpeed = max(1, (float) ($production['printing_speed'] ?? $defaults['book_printing_speed'] ?? 2500));
        $foldingSpeed = max(1, (float) ($production['folding_speed'] ?? $defaults['book_folding_speed'] ?? 2500));
        $collatingSpeed = max(1, (float) ($production['collating_speed'] ?? $defaults['book_collating_speed'] ?? 2500));
        $laminatingSpeed = max(1, (float) ($production['laminating_speed'] ?? $defaults['book_laminating_speed'] ?? 300));
        $perfectBindingSpeed = max(1, (float) ($production['perfect_binding_speed'] ?? $defaults['book_perfect_binding_speed'] ?? 700));
        $gluingSpeed = max(1, (float) ($production['gluing_speed'] ?? $defaults['book_gluing_speed'] ?? 25));
        $packingMultiplier = max(1, (float) ($production['packing_multiplier'] ?? $defaults['book_packing_multiplier'] ?? 12));

        $printingHours = $this->printingHours($quantity, $textPlates, $coverPlates, $printingSpeed, $size, $pages, $binding, $insidePrinting);
        $foldingHours = ($size === 'A4' ? $quantity * $pages / 8 : $quantity * $pages / 16) / $foldingSpeed;
        $collatingHours = $foldingHours;
        $cuttingHours = $this->ceil(($textPaperSheets * 2) / 10000);
        $laminatingHours = $quantity / $laminatingSpeed;
        $perfectBindingHours = $this->isPerfect($binding) ? $quantity / $perfectBindingSpeed : 0;
        $gluingHours = $this->isHardCover($binding) ? $quantity / $gluingSpeed : 0;
        $packingHours = $this->ceil($quantity / ($this->booksPerPack($pages) * $packingMultiplier));

        $labourLines = [
            $this->line('Labour', 'Typesetting', $pages, 'page', (float) ($production['typesetting_rate'] ?? $defaults['book_typesetting_rate'] ?? 20)),
            $this->line('Labour', 'Artwork', (float) ($production['artwork_hours'] ?? $defaults['book_artwork_hours'] ?? 3), 'hour', (float) ($production['artwork_rate'] ?? $defaults['book_artwork_rate'] ?? 600)),
            $this->line('Labour', 'Make ready', (float) ($defaults['book_make_ready_hours'] ?? 0), 'hour', (float) ($defaults['book_make_ready_rate'] ?? 0)),
            $this->line('Machine', 'Printing', $printingHours, 'hour', (float) ($production['printing_rate'] ?? $defaults['book_printing_rate'] ?? 200), null, ['costing_speed' => $printingSpeed]),
            $this->line('Machine', 'Folding', $foldingHours, 'hour', (float) ($production['folding_rate'] ?? $defaults['book_folding_rate'] ?? 175), null, ['costing_speed' => $foldingSpeed]),
            $this->line('Labour', 'Collating', $collatingHours, 'hour', (float) ($production['collating_rate'] ?? $defaults['book_collating_rate'] ?? 0.05)),
            $this->line('Machine', 'Cutting', $cuttingHours, 'hour', (float) ($production['cutting_rate'] ?? $defaults['book_cutting_rate'] ?? 100)),
            $this->line('Machine', 'Laminating', $laminatingHours, 'hour', (float) ($production['laminating_rate'] ?? $defaults['book_laminating_rate'] ?? 80), null, ['costing_speed' => $laminatingSpeed]),
            $this->line('Machine', 'Perfect binding', $perfectBindingHours, 'hour', (float) ($production['perfect_binding_rate'] ?? $defaults['book_perfect_binding_rate'] ?? 140), null, ['costing_speed' => $perfectBindingSpeed]),
            $this->line('Machine', 'Gluing', $gluingHours, 'hour', (float) ($production['gluing_rate'] ?? $defaults['book_gluing_rate'] ?? 60), null, ['costing_speed' => $gluingSpeed]),
            $this->line('Labour', 'Packing', $packingHours, 'hour', (float) ($production['packing_rate'] ?? $defaults['book_packing_rate'] ?? 30)),
        ];

        $commercial = [
            'overhead_percent' => $commercial['overhead_percent'] ?? 15,
            'profit_margin_percent' => $commercial['profit_margin_percent'] ?? 25,
            'discount_percent' => $commercial['discount_percent'] ?? 0,
        ];

        return $this->commercialTotals([...$materialLines, ...$labourLines], $quantity, $commercial, $settings);
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y'], true);
    }

    private function ceil(float $value): float
    {
        return (float) ceil($value);
    }

    private function normalizedBinding(string $binding): string
    {
        return strtolower(str_replace('_', ' ', $binding));
    }

    private function isSaddle(string $binding): bool
    {
        return $this->normalizedBinding($binding) === 'saddle';
    }

    private function isPerfect(string $binding): bool
    {
        return $this->normalizedBinding($binding) === 'perfect';
    }

    private function isHardCover(string $binding): bool
    {
        return $this->normalizedBinding($binding) === 'hard cover';
    }

    private function textSheetsPerParent(string $size): int
    {
        return match ($size) {
            'A4' => 16,
            'A5', 'B5' => 32,
            'A6', 'B6' => 64,
            'B7' => 128,
            default => 1,
        };
    }

    private function textAllowanceDivisor(string $size): int
    {
        return match ($size) {
            'A4' => 8,
            'A5', 'B5' => 16,
            'A6', 'B6' => 32,
            'B7' => 64,
            default => 1,
        };
    }

    private function coverPaperSheets(int $quantity, int $pages, string $size, string $coverPaperFormat): float
    {
        if (str_starts_with($size, 'A')) {
            if ($pages <= 300) {
                return match ($size) {
                    'A4' => $quantity / 4,
                    'A5' => $quantity / 8,
                    default => $quantity / 16,
                };
            }

            return match ($size) {
                'A4' => $quantity / 3,
                'A5' => $quantity / 5,
                default => $quantity / 11,
            };
        }

        if ($coverPaperFormat === 'A1') {
            return match ($size) {
                'B5' => $quantity / 4,
                'B6' => $quantity / 9,
                default => $quantity / 16,
            };
        }

        if ($pages <= 300) {
            return match ($size) {
                'B5' => $quantity / 8,
                'B6' => $quantity / 16,
                default => $quantity / 32,
            };
        }

        return match ($size) {
            'B5' => $quantity / 5,
            'B6' => $quantity / 11,
            default => $quantity / 24,
        };
    }

    private function casePaperSheets(int $quantity, string $size): float
    {
        return match ($size) {
            'A4' => $quantity / 2,
            'A5', 'B5' => $quantity / 4,
            'A6' => $quantity / 8,
            'B6' => $quantity / 6,
            'B7' => $quantity / 10,
            default => 0,
        };
    }

    private function greyBoardSheets(int $quantity, string $size): float
    {
        return match ($size) {
            'A4' => $quantity / 4.5,
            'A5' => $quantity / 10,
            'A6' => $quantity / 18,
            'B5' => $quantity / 8,
            'B6' => $quantity / 16,
            'B7' => $quantity / 32,
            default => 0,
        };
    }

    private function endsheetSheets(int $quantity, string $size): float
    {
        return match ($size) {
            'A4' => $quantity / 2,
            'A5', 'B5' => $quantity / 4,
            'A6', 'B6' => $quantity / 8,
            'B7' => $quantity / 16,
            default => 0,
        };
    }

    private function textPlates(int $pages, string $size): float
    {
        return match ($size) {
            'A4' => $pages / 4,
            'A5', 'B5' => $pages / 8,
            'A6', 'B6' => $pages / 16,
            'B7' => $pages / 32,
            default => 0,
        };
    }

    private function inkKg(string $size, int $pages, int $quantity, int $textColors, int $coverColors, float $textCoverage, float $coverCoverage): float
    {
        [$textWidth, $textHeight, $coverWidth, $coverHeight] = match ($size) {
            'A4' => [0.21, 0.297, 0.297, 0.43],
            'A5' => [0.21, 0.15, 0.297, 0.21],
            'A6' => [0.21, 0.145, 0.297, 0.21],
            'B5' => [0.175, 0.25, 0.25, 0.35],
            'B6' => [0.175, 0.125, 0.25, 0.175],
            'B7' => [0.0875, 0.125, 0.25, 0.175],
            default => [0, 0, 0, 0],
        };

        return (($textWidth * $textHeight * $pages * 0.3 * $quantity + 350) * $textColors * $textCoverage
            + ($coverWidth * $coverHeight * 0.4 * $quantity + 350) * $coverColors * $coverCoverage) / 1000;
    }

    private function laminationSquareMeters(string $binding, string $size, int $pages, int $quantity): float
    {
        if (($this->isSaddle($binding) || $this->isPerfect($binding)) && $pages <= 300) {
            return $this->ceil(match ($size) {
                'A4' => 0.43 * 0.3 * $quantity * 1.05,
                'A5' => (0.43 / 2) * 0.3 * $quantity * 1.05,
                'A6' => (0.43 / 4) * 0.3 * $quantity * 1.05,
                'B5' => 0.43 * 0.25 * $quantity * 1.05,
                'B6' => (0.43 / 2) * 0.25 * $quantity * 1.05,
                'B7' => (0.43 / 4) * 0.25 * $quantity * 1.05,
                default => 0,
            });
        }

        if ($this->isSaddle($binding) || $this->isPerfect($binding)) {
            return $this->ceil(match ($size) {
                'A4' => 0.43 * 0.46 * $quantity * 1.05,
                'A5' => 0.43 * 0.21 * $quantity * 1.05,
                'A6' => 0.43 * (0.25 / 2) * $quantity * 1.05,
                'B5' => 0.43 * 0.25 * $quantity * 1.05,
                'B6' => 0.43 * 0.17 * $quantity * 1.05,
                'B7' => 0.43 * (0.23 / 2) * $quantity * 1.05,
                default => 0,
            });
        }

        if (! $this->isHardCover($binding)) {
            return 0;
        }

        return $this->ceil(match ($size) {
            'A4' => 0.43 * 0.52 * $quantity * 1.05,
            'A5' => 0.43 * 0.26 * $quantity * 1.05,
            'A6' => 0.43 * (0.3 / 2) * $quantity * 1.05,
            'B5' => 0.43 * 0.45 * $quantity * 1.05,
            'B6' => 0.43 * 0.23 * $quantity * 1.05,
            'B7' => 0.43 * (0.28 / 2) * $quantity * 1.05,
            default => 0,
        });
    }

    private function printingHours(int $quantity, float $textPlates, float $coverPlates, float $speed, string $size, int $pages, string $binding, bool $insidePrinting): float
    {
        $shortRunBound = ($this->isSaddle($binding) || $this->isPerfect($binding)) && $pages <= 300;
        $coverMultiplier = 1.0;

        if (! $insidePrinting) {
            if ($shortRunBound) {
                $coverMultiplier = match ($size) {
                    'A5' => 0.5,
                    'A6', 'B7' => 0.25,
                    default => 1.0,
                };
            } elseif ($size === 'B7') {
                $coverMultiplier = 0.25;
            }
        } elseif ($shortRunBound) {
            $coverMultiplier = match ($size) {
                'A5' => 1.0,
                'A6', 'B7' => 0.5,
                default => 2.0,
            };
        } elseif ($size === 'B7') {
            $coverMultiplier = 0.5;
        } else {
            $coverMultiplier = 2.0;
        }

        return ($quantity * $textPlates + $quantity * $coverPlates * $coverMultiplier) / $speed;
    }

    private function booksPerPack(int $pages): int
    {
        if ($pages >= 400) {
            return 10;
        }

        if ($pages >= 300) {
            return 16;
        }

        if ($pages >= 200) {
            return 20;
        }

        if ($pages >= 50) {
            return 40;
        }

        return 100;
    }
}
