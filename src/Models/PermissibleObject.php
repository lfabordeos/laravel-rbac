<?php

namespace RRRBAC\Models;

use Illuminate\Database\Eloquent\Model;

class PermissibleObject extends Model
{
    public function getOptionsToArrayAttribute()
    {
        return json_decode($this->options, true);
    }
}
