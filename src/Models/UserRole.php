<?php

namespace RRRBAC\Models;

use RRRBAC\Models\Role;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
}
