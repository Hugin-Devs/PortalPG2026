<?php

namespace App\Models;

use App\Models\Concerns\StoresLatin9;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Modelo base del portal: todos los modelos extienden de aquí para heredar
 * la sanitización LATIN9 (ver App\Models\Concerns\StoresLatin9).
 */
abstract class Model extends EloquentModel
{
    use StoresLatin9;
}
