<?php

namespace RRRBAC\Services;

use RRRBAC\Exceptions\UnauthorizedAccessException;
use RRRBAC\Repositories\RBACRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Str;

class RoleService
{
    use RouteDependencyResolverTrait;

    const ROUTE_PATTERN = "/{([A-Za-z0-9\-\_]+)}/";
    const NAME_PATTERN  = "/^[A-Za-z][A-Za-z0-9\-\_]+/";

    protected $RBACRepository;

    public function __construct(RBACRepository $RBACRepository)
    {
        $this->RBACRepository = $RBACRepository;
    }

    /**
     * Checks if received request is authorized
     *
     * @param  Request $request
     * @return bool
     */
    public function canAccess(Request $request) : bool
    {
        // Set request
        $this->request = $request;

        $user = $this->getUser();

        if (empty($user)) {
            return false;
        }

        $rawUri     = $this->getRawURI();
        $route      = $this->formatRoute($rawUri);
        $permission = $this->RBACRepository->getPermission($user, $route, $this->request->method());

        if (empty($permission) || !$permission->is_allowed) {
            return false;
        }

        if (!$this->hasDynamicMarkers($permission->route)) {
            return true;
        }

        return $this->hasDynamicAccess(
            $user,
            $permission,
            $this->extractObjects($rawUri)
        );
    }

    /**
     * Format route by converting path varialbes into dynamic markers
     * @param  string $route
     * @return string
     */
    public function formatRoute($route) : string
    {
        return preg_replace(self::ROUTE_PATTERN, '*', $route);
    }

    /**
     * Extract permissible object names
     *
     * @param  string $route
     * @return array
     */
    public function extractObjects($route) : array
    {
        $objects = [];
        $slugs = explode('/', ltrim($route, '/'));

        foreach ($slugs as $key => $slug) {
            if ($key == 0) continue;

            if ($this->isParameterPatternMatch($slug)) {
                $objects[] = [
                    'name'    => $slugs[$key-1],
                    'is_last' => ($key >= count($slugs)-1)
                ];
            } else if (($key == (count($slugs)-1))
                && $this->isObjectNamePatternMatch($slug)
            ) {
                $objects[] = [
                    'name'    => $slugs[$key],
                    'is_last' => true
                ];
            }
        }

        return $objects;
    }

    /**
     * Get the user to be checked
     *
     * @return mixed
     */
    protected function getUser()
    {
        return $this->request->user();
    }

    protected function extractRouteParams() : array
    {
        list($controller, $method) = Str::parseCallback($this->request->route()->action['controller']);
        return $this->resolveClassMethodDependencies($this->request->route()->parametersWithoutNulls(), $controller, $method);
    }

    /**
     * Get the raw URI, including path variables
     *
     * @return string
     */
    protected function getRawURI()
    {
        return '/'.ltrim($this->request->route()->uri, '/');
    }

    /**
     * Checks if given slug matches path variable pattern
     *
     * @param  string  $slug
     * @return boolean
     */
    protected function isParameterPatternMatch($slug) : bool
    {
        return preg_match(self::ROUTE_PATTERN, $slug);
    }

    /**
     * Checks if given slug matches object name pattern
     *
     * @param  string  $slug
     * @return boolean
     */
    protected function isObjectNamePatternMatch($slug) : bool
    {
        return preg_match(self::NAME_PATTERN, $slug);
    }

    /**
     * Checks if the given URI has dynamic markers '*'
     *
     * @param  string  $route
     * @return boolean
     */
    protected function hasDynamicMarkers($route) : bool
    {
        return stripos($route, '/*') !== false;
    }

    /**
     * Checks if the given user has
     *
     * @param mixed      $user
     * @param Permission $permission
     * @param array      $objects
     * @return boolean
     */
    protected function hasDynamicAccess($user, $permission, $objects) : bool
    {
        foreach ($objects as $object) {
            if ((!$object['is_last'] && !$this->canViewObject($user, $object['name']))
                || ($object['is_last'] && !$this->canAccessObject($user, $object['name'], $this->requestMethodToAccessMethod()))
            ) {
                    throw new UnauthorizedAccessException;
            }
        }

        return true;
    }

    /**
     * Check if current user can view object
     *
     * @param  User $user
     * @param  string $objectName
     * @return boolean
     */
    protected function canViewObject($user, $objectName) : bool
    {
        return $this->canAccessObject($user, $objectName, 'viewable_by');
    }

    /**
     * Check if current user can access object using provided access method
     *
     * @param  User $user
     * @param  string $objectName
     * @param  string $accessMethod
     * @return boolean
     */
    protected function canAccessObject($user, $objectName, $accessMethod) : bool
    {
        $object = $this->RBACRepository->getObject($user, $objectName, $accessMethod);

        if (empty($object) || empty($object->optionsToArray[$accessMethod])) {
            return false;
        }

        $options = $object->optionsToArray[$accessMethod];

        if (in_array('all', $options)) {
            return true;
        }

        if (in_array($user->getRole(), $options)) {
            return true;
        }

        if (in_array('owner', $options)
            && $this->isUserObjectOwner($user, $object)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if current user is the owner of object being accessed
     *
     * @param  User $user
     * @param  PermissibleObject $object
     * @return boolean
     */
    protected function isUserObjectOwner($user, $object) : bool
    {
        $objectId = $this->extractObjectId($object);

        return !empty(app($object->ownable_type)->where([
            [$object->ownable_column, $this->getOwnerId($user, $object->owner_object)],
            ['id', $objectId]
        ])
        ->first());
    }

    /**
     * Recurrsively tries to get object's owner id from user
     *
     * @param User $user
     * @param string $string
     * @return mixed
     */
    protected function getOwnerId($user, $ownerObject)
    {
        if (empty($ownerObject)) {
            return $user->id;
        }

        $objectNames = explode('.', $ownerObject);

        return $this->RBACRepository->getOwnerObjectId($user, explode('.', $ownerObject));
    }

    /**
     * Extract Objcect instance ID from url
     *
     * @param  PermissibleObject $object
     * @return mixed
     */
    protected function extractObjectId($object)
    {
        $string = substr($this->request->url(), stripos($this->request->url(), $object->name)+Str::length($object->name)+1);

        return stripos($string, '/') !== false ? substr($string, 0, stripos($string, '/')) : $string;
    }

    /**
     * Translate request methods into permissible object colums
     *
     * @return string
     */
    protected function requestMethodToAccessMethod() : string
    {
        switch($this->request->method())
        {
            case 'GET': return 'viewable_by';
            case 'POST' : return 'creatable_by';
            case 'PUT':
            case 'PATCH': return 'editable_by';
            case 'DELETE': return 'deletable_by';
        }
    }
}
