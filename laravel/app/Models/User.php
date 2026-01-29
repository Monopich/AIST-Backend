<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'telegram_user_id',
        'otp_code',
        'otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // 'created_at',
        // 'updated_at',
        'email_verified_at',
        'deleted_at',
        'pivot',
        'otp_code',
        'otp_expires_at',
        'telegram_user_id',

    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }




    // relationships

    public function userDetail()
    {
        return $this->hasOne(UserDetail::class, 'user_id');
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
    public function generations()
    {
        return $this->belongsToMany(Generation::class, 'user_programs', 'generation_id', 'user_id');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_users', 'user_id', 'group_id');
    }
    public function headDepartment()
    {
        return $this->hasOne(Department::class, 'department_head_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_teachers', 'user_id', 'subject_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendanceTrackings(){
        return $this->hasMany(AttendanceTracking::class);
    }

    // check user role
    public function hasRole($roleKey)
    {
        return $this->userRoles()->whereHas('role', function ($query) use ($roleKey) {
            $query->where('role_key', $roleKey);
        })->exists();
    }

    public function hasAnyRole(array $roleKeys): bool
    {
        return $this->userRoles()->whereHas('role', function ($query) use ($roleKeys) {
            $query->whereIn('role_key', $roleKeys);
        })->exists();
    }

    public function assignRole($roleKey)
    {
        $role = Role::where('role_key', $roleKey)->first();

        if (!$role) {
            throw new \Exception("Invalid role key.");
        }

        // If user already has the role, throw exception
        if ($this->roles()->where('role_id', $role->id)->exists()) {
            throw new \Exception("User already has the role '$roleKey'.");
        }

        // Otherwise attach once
        $this->roles()->attach($role->id);
    }


    public function removeRole($roleKey)
    {
        $role = Role::where('role_key', $roleKey)->first();

        if (!$role) {
            throw new \InvalidArgumentException("Invalid role key: $roleKey");
        }

        if (!$this->roles()->where('role_id', $role->id)->exists()) {
            throw new \Exception("User does not have the role '$roleKey'.");
        }

        $this->roles()->detach($role->id);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function userPrograms()
    {
        return $this->hasMany(UserProgram::class, 'user_id');
    }


    public function missions()
    {
        return $this->belongsToMany(Mission::class, 'user_missions');
    }


}
