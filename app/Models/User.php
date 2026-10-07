<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use App\Models\Business\Branch;
use App\Models\Business\Employee;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Models\Settings\ModulePermission;
use App\Models\Settings\Module;

/**
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon $email_verified_at
 * @property string $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'photo_url',
        'google_avatar_url',
        'role',
        'emp_id',
        'branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function employee()
    {
        return $this->hasOne(Employee::class, 'id', 'emp_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }

    public function isCustomer(): bool
    {
        return ! $this->isManager();
    }

    public function canManageCourts(): bool
    {
        return $this->isManager();
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function hasPermission($module)
    {
        $moduleId = Module::where('module_name', $module)->value('id');

        if (!$moduleId) {
            return false; // or handle the case when the module is not found
        }
        $access = ModulePermission::where([['module_id', $moduleId], ['employee_id', $this->emp_id]])->first();
        return $access->access ?? false;
    }

    public function hasAccess($moduleGroup)
    {
        $moduleId = Module::where('group_name', $moduleGroup)->get('id');
        if (!$moduleId) {
            return false; // or handle the case when the module is not found
        }
        $access = ModulePermission::whereIn('module_id', $moduleId)->where('employee_id', $this->emp_id)->first();
        return $access->access ?? false;
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        if ($this->google_avatar_url) {
            return $this->google_avatar_url;
        }

        $name = urlencode($this->name ?: 'User');

        return "https://ui-avatars.com/api/?name={$name}&color=84cc16&background=0f172a&bold=true";
    }
}
