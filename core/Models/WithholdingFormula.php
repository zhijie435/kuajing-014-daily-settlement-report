<?php

namespace App\Models;

class WithholdingFormula extends Model
{
    protected $table = 'withholding_formulas';

    protected $fillable = [
        'formula_code', 'formula_name', 'formula', 'variables',
        'description', 'is_enabled', 'sort_order'
    ];

    public function findByCode(string $code)
    {
        return $this->findBy('formula_code', $code);
    }

    public function getActive(): array
    {
        return $this->where('is_enabled = 1', [], 'sort_order ASC');
    }
}
