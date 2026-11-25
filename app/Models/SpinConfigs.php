<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpinConfigs extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    use HasFactory;

    protected $appends = ['prizesArray'];
    protected $table = 'ggds_spin_config';

    protected $fillable = [
        'prizes'
    ];

    /**
     * @return mixed
     */
    public function getPrizesArrayAttribute(): mixed
    {
        return json_decode($this->attributes['prizes'], true);
    }

    /** The current casino's spin configuration, created empty (no prizes) if missing. */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(['prizes' => '[]']);
    }
}
