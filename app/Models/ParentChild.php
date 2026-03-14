<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ParentChild extends Pivot
{
    protected $table = 'parent_child';

    protected $fillable = [
        'parent_id',
        'student_id',
    ];
}
