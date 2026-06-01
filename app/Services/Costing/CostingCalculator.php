<?php

namespace App\Services\Costing;

interface CostingCalculator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function calculate(array $data): CostingResult;
}
