<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Role|string $role
 * @property bool $status_active
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status_active',
    ];

    /**
     * Atribut yang harus disembunyikan untuk serialisasi.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status_active' => 'boolean',
            'role' => Role::class,
        ];
    }

    /**
     * Prepare a date for array / JSON serialization.
     * Override untuk format konsisten tanpa timezone
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    // ========== Metode JWT ==========

    /**
     * Mendapatkan identifier yang akan disimpan di JWT subject claim.
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Mengembalikan array key-value custom claims untuk ditambahkan ke JWT.
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        $role = is_object($this->role) ? $this->role->value : $this->role;
        return [
            'uid' => $this->id,
            'name' => $this->name,
            'role' => $role,
        ];
    }    // ========== Relasi ==========

    /**
     * Relasi 1:1 dengan Employee
     * Satu user memiliki satu profil karyawan
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Relasi 1:N dengan Employee (sebagai manager)
     * Satu manager membawahi banyak karyawan
     */
    public function managedEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * Relasi 1:1 dengan Department (sebagai manager)
     * Satu user mengelola satu departemen
     */
    public function managedDepartment()
    {
        return $this->hasOne(Department::class, 'manager_id');
    }

    /**
     * Relasi 1:N dengan LeaveRequest (sebagai reviewer)
     * User yang mereview banyak pengajuan cuti
     */
    public function reviewedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'reviewed_by');
    }

    /**
     * Relasi 1:N dengan PerformanceReview (sebagai reviewer)
     * Manager yang memberikan banyak penilaian kinerja
     */
    public function givenPerformanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'reviewer_id');
    }

    /**
     * Relasi 1:N dengan SalarySlip (sebagai creator)
     * Admin HR yang membuat banyak slip gaji
     */
    public function createdSalarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class, 'created_by');
    }

    /**
     * Relasi 1:N dengan Notification
     * User memiliki banyak notifikasi
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    // ========== Helper Methods ==========

    /**
     * Scope untuk filter user yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status_active', true);
    }

    // ========== Metode Helper ==========

    /**
     * Cek apakah user adalah Admin HR
     */
    public function isAdminHr(): bool
    {
        $role = is_object($this->role) ? $this->role->value : $this->role;
        return $role === 'admin_hr';
    }

    /**
     * Cek apakah user adalah Manager
     */
    public function isManager(): bool
    {
        $role = is_object($this->role) ? $this->role->value : $this->role;
        return $role === 'manager';
    }

    /**
     * Cek apakah user adalah Employee
     */
    public function isEmployee(): bool
    {
        $role = is_object($this->role) ? $this->role->value : $this->role;
        return $role === 'employee';
    }
}
