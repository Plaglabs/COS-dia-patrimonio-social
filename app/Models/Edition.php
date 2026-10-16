<?php

namespace App\Models;

use App\Services\SanitizadorHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Edition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['anio', 'titulo', 'descripcion', 'imagen', 'enlace', 'activo'];

    protected function casts(): array
    {
        return ['anio' => 'integer', 'activo' => 'boolean'];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('anio');
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? asset($this->imagen) : null;
    }

    /**
     * A dónde lleva la tarjeta del home, ya filtrado: sólo http(s), mailto,
     * tel o una ruta del propio sitio. Vacío, la tarjeta no es un enlace.
     */
    public function destino(): string
    {
        return app(SanitizadorHtml::class)->enlace($this->enlace);
    }
}
