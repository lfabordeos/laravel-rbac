<?php

namespace RRRBAC\Repositories;

use RRRBAC\Models\Permission;
use RRRBAC\Models\Role;
use RRRBAC\Models\RolePermission;
use RRRBAC\Models\UserRole;

class RBACRepository
{

    /**
     * @var RBACExtended\Models\RoleModel
     */
    protected $roleModel;

    /**
     * @var RBACExtended\Models\RolePermission
     */
    protected $rolePermissionModel;

    /**
     * @var RBACExtended\Models\Permission
     */
    protected $permissionModel;

    /**
     * @var RBACExtended\Models\UserRole
     */
    protected $userRoleModel;

    public function __construct(
        Role $roleModel,
        RolePermission $rolePermissionModel,
        Permission $permissionModel,
        UserRole $userRoleModel
    ) {
        $this->roleModel           = $roleModel;
        $this->rolePermissionModel = $rolePermissionModel;
        $this->permissionModel     = $permissionModel;
        $this->userRoleModel       = $userRoleModel;
    }

    public function getPermission($user, $route)
    {
        $permission = $this->permissionModel
            ->newQuery()
            ->select('roles.id', 'roles.weight', 'permissions.*')
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('user_roles', function($join) use ($user) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->where('user_roles.user_id',$user->id);
            })
            ->orderBy('roles.weight','DESC')
            ->take(1)
            ->first();

    }
}
