<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeacherClass extends Pivot
{
    protected $table = 'teacher_class';

    protected $fillable = [
        'teacher_id',
        'class_id',
        'subject_id',
    ];
}
