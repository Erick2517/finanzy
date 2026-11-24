<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoFinanciero extends Model
{
    protected $table = 'movimientos_financieros';

    protected $fillable = ['user_id', 'categoria_id', 'monto', 'fecha', 'concepto', 'activo'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'activo' => 'boolean'];
    }

    #[Scope]
    protected function activos(Builder $query): void
    {
        $query->where('activo', true);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
