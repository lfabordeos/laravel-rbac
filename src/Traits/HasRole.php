<?php

namespace RRRBAC\Traits;

use RRRBAC\Models\UserRole;

trait HasRole
{
    public function getRole()
    {
        return $this->hasOne(UserRole::class)->getResults()->role;
    }
}
