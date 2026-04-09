<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'flag', 'is_active', 'sort_order'])]
class Language extends Model
{
    // primary key is the language code, not an int
    protected $primaryKey = 'code';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $casts = ['is_active' => 'boolean'];
}
