<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes';

    protected $fillable = [
        'code',
        'name',
        'level',
    ];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_teacher', 'class_id', 'user_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'class_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'class_id');
    }
}
