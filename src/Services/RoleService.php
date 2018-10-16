<?php

namespace RRRBAC\Services;

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
        $this->container      = app();
    }

    /**
     * Checks if received request is authorized
     *
     * @param  Request $request
     * @return bool
     */
    public function canAccess(Request $request) : bool
    {
        $rawUri = $this->getRawURI($request);

        $routeParameters = $this->extractRouteParams($request);
        $user            = $this->getUser($request);
        $route           = $this->formatRoute($rawUri);dd($this->extractObjects($rawUri));
        $permission      = $this->RBACRepository->getPermission($user, $route);

        if (empty($permission)) {
            return false;
        }

        if (!$this->hasDynamicMarkers($permission->route)) {
            return true;
        }

        return $this->hasDynamicAccess(
            $request,
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
     * @param  Request $request
     * @return mixed
     */
    protected function getUser(Request $request)
    {
        return $request->user();
    }

    protected function extractRouteParams(Request $request) : array
    {
        list($controller, $method) = Str::parseCallback($request->route()->action['controller']);
        return $this->resolveClassMethodDependencies($request->route()->parametersWithoutNulls(), $controller, $method);
    }

    /**
     * Get the raw URI, including path variables
     *
     * @param  Illuminate\Http\Request $request
     * @return string
     */
    protected function getRawURI(Request $request)
    {
        return '/'.ltrim($request->route()->uri, '/');
    }

    /**
     * Checks if given slug matches path variable pattern
     *
     * @param  string  $slug
     * @return boolean
     */
    protected function isParameterPatternMatch($slug) : bool
    {
        return preg_match(self::ROUTE_PATTERN, $slug)
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
     * @param string     $requestMethod
     * @param array      $objects
     * @return boolean
     */
    protected function hasDynamicAccess($user, $permission, $requestMethod, $objects) : bool
    {
        # TODO
    }

    /**
     * Translate request methods into permissible object colums
     *
     * @param  string $requestMethod
     * @return string
     */
    protected function requestMethodToAccessMethod($requestMethod) : string
    {
        switch($requestMethod)
        {
            case 'GET': return 'viewable_by';
            case 'POST' : return 'creatable_by';
            case 'PUT':
            case 'PATCH': return 'editable_by';
            case 'DELETE': return 'deletable_by';
        }
    }
}
