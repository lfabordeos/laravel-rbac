<?php

namespace RRRBAC\Repositories;

use RRRBAC\Exceptions\InvalidAccessMethodGivenException;
use RRRBAC\Exceptions\InvalidObjectNameGivenException;
use RRRBAC\Models\PermissibleObject;
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
        UserRole $userRoleModel,
        PermissibleObject $permissibleObjectModel
    ) {
        $this->roleModel              = $roleModel;
        $this->rolePermissionModel    = $rolePermissionModel;
        $this->permissionModel        = $permissionModel;
        $this->userRoleModel          = $userRoleModel;
        $this->permissibleObjectModel = $permissibleObjectModel;
    }

    public function getPermission($user, $route, $method)
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
            ->where('route', '/'.ltrim($route, '/'))
            ->whereIn('method', ['ALL', $method])
            ->orderBy('roles.weight','DESC')
            ->take(1)
            ->first();

        return $permission;
    }

    public function getObject($user, $objectName, $accessMethod)
    {
        $object = $this->permissibleObjectModel
            ->where('name', $objectName);


        $object = $this->accessQuery($object, $accessMethod, $user->getRole())
            ->first();

        return $object;

    }

    public function getObjectByName($objectName)
    {
        return $this->permissibleObjectModel->where('name', $objectName)
            ->firstOrFail();
    }

    public function getOwnerObjectId($user, $objectNames)
    {
        $objects = $this->permissibleObjectModel->whereIn('name', $objectNames)->get();

        if (empty($objects)) {
            return false;
        }

        $objectNames = array_flip ($objectNames);

        $objects->each(function ($item) use (&$objectNames) {
            $objectNames[$item->name] = [
                'object' => $item,
                'table'  => app($item->ownable_type)->getTable()
            ];
        });

        $rootObjectName = current($objectNames);
        $lastObjectName = end($objectNames);

        $query = app($rootObjectName['object']->ownable_type)
                ->select($rootObjectName['table'].'.id');

        $position       = 0;
        $prevObjectName = $rootObjectName;
        foreach ($objectNames as $objectName) {
            if ($position++ == 0) {
                continue;
            }

            $query->join(
                $objectName['table'],
                $objectName['table'].'.id',
                '=',
                $prevObjectName['table'].'.'.$prevObjectName['object']->ownable_column
            );

            $prevObjectName = $objectName;
        }

        $query->join(
                'users',
                'users.id',
                '=',
                "{$lastObjectName['table']}.".$lastObjectName['object']->ownable_column
            )
            ->where('users.id', $user->id);

        return $query->first()->id;
    }

    protected function accessQuery($query, $accessMethod, $role)
    {
        switch ($accessMethod) {
            case 'viewable_by': $query->where(function($query) use ($role){
                                    $query->whereJsonContains('options->viewable_by', "owner")
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->viewable_by', $role);
                                        })
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->viewable_by', "all");
                                        });
                                });
                break;
            case 'creatable_by': $query->where(function($query) use ($role){
                                    $query->whereJsonContains('options->creatable_by', $role)
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->creatable_by', "all");
                                        });
                                });
                break;
            case 'editable_by': $query->where(function($query) use ($role){
                                    $query->whereJsonContains('options->editable_by', "owner")
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->editable_by', $role);
                                        })
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->editable_by', "all");
                                        });
                                });
                break;
            case 'deletable_by': $query->where(function($query) use ($role){
                                    $query->whereJsonContains('options->deletable_by', "owner")
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->deletable_by', $role);
                                        })
                                        ->orWhere(function($query) use ($role) {
                                            $query->whereJsonContains('options->deletable_by', "all");
                                        });
                                });
                break;
            default:
                throw new InvalidAccessMethodGivenException;
        }

        return $query;
    }
}
