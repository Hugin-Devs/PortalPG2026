<?php

namespace App\Models\Concerns;

use App\Support\Latin9;

/**
 * Translitera automáticamente a LATIN9 seguro todo texto que se asigna a un
 * atributo del modelo (fillable o no), antes de los mutadores y de los
 * casts `encrypted` (ver DOCUMENTACION.md §14.K).
 *
 * La base de datos de producción es PostgreSQL LATIN9: cualquier carácter
 * sin equivalente (emoji, “ ”, …, —) provocaría el error 22021. Al
 * sanitizar en setAttribute se cubren creaciones, actualizaciones, fill(),
 * updateOrCreate() y asignaciones directas ($model->title = ...).
 */
trait StoresLatin9
{
    public function setAttribute($key, $value)
    {
        if (is_string($value)) {
            $value = Latin9::sanitize($value);
        }

        return parent::setAttribute($key, $value);
    }
}
