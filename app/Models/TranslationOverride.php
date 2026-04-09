<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['language_code', 'key', 'value'])]
class TranslationOverride extends Model
{
}
