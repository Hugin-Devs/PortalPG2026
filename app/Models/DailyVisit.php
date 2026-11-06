<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class DailyVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'day',
        'count',
    ];

    protected $casts = [
        'day' => 'date',
        'count' => 'integer',
    ];

    /**
     * Suma de visitas de todas las filas diarias (contador acumulado).
     */
    public static function total(): int
    {
        return (int) static::query()->sum('count');
    }

    /**
     * Registra una visita en el día indicado con un upsert atómico sobre la
     * PK `day` (mismo patrón que daily_views en NewsController@show).
     */
    public static function record(string $day): void
    {
        DB::table('daily_visits')->upsert(
            [['day' => $day, 'count' => 1]],
            ['day'],
            ['count' => DB::raw('daily_visits.count + 1')]
        );
    }
}
