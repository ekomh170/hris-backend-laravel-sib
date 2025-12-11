<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'manager_id'];

    /**
     * Relasi 1:N dengan Manager
     *  Setiap departmen memiliki satu manager
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Relasi N:1 dengan Employee
     * Setiap departemen memiliki banyak karyawan
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}