<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ApiEndpointRegistry
{
    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Build a map of URI patterns to controller targets that CodeIgniter can consume.
     *
     * @return array
     */
    public function buildRouteMap()
    {
        $routeMap  = [];
        $versions  = isset($this->config['versions']) && is_array($this->config['versions']) ? $this->config['versions'] : [];

        foreach ($versions as $versionConfig) {
            $versionPrefix = $this->normalizeSegment($versionConfig['prefix'] ?? '');
            $resources     = isset($versionConfig['resources']) && is_array($versionConfig['resources']) ? $versionConfig['resources'] : [];

            foreach ($resources as $resource) {
                $controller  = $this->normalizeController($resource['controller'] ?? '');
                $groupPrefix = $this->normalizeSegment($resource['group_prefix'] ?? '');

                if ($controller === '') {
                    continue;
                }

                $resourceRoutes = isset($resource['routes']) && is_array($resource['routes']) ? $resource['routes'] : [];

                foreach ($resourceRoutes as $resourceRoute) {
                    $path   = $this->normalizePath($resourceRoute['path'] ?? '');
                    $action = $this->normalizePath($resourceRoute['action'] ?? '');

                    if ($action === '') {
                        continue;
                    }

                    $uriSegments = array_filter([
                        $versionPrefix,
                        $groupPrefix,
                        $path,
                    ], function ($segment) {
                        return $segment !== '';
                    });

                    $uri = implode('/', $uriSegments);

                    if ($uri === '') {
                        continue;
                    }

                    $target = $this->buildTarget($controller, $action);

                    if ($target === '') {
                        continue;
                    }

                    $routeMap[$uri] = $target;

                    if (!empty($resourceRoute['with_trailing_slash'])) {
                        $routeMap[$uri . '/'] = $target;
                    }
                }
            }
        }

        return $routeMap;
    }

    /**
     * Retrieve a sanitized copy of the endpoint definitions that can be used for
     * documentation or validation purposes.
     *
     * @return array
     */
    public function getEndpointDefinitions()
    {
        $definitions = [
            'default_version' => isset($this->config['default_version']) && is_string($this->config['default_version'])
                ? $this->config['default_version']
                : null,
            'versions'        => [],
        ];

        $versions = isset($this->config['versions']) && is_array($this->config['versions']) ? $this->config['versions'] : [];

        foreach ($versions as $versionKey => $versionConfig) {
            $versionPrefix = $this->normalizeSegment($versionConfig['prefix'] ?? '');
            $resources     = isset($versionConfig['resources']) && is_array($versionConfig['resources']) ? $versionConfig['resources'] : [];

            $definitionResources = [];

            foreach ($resources as $resource) {
                $controller  = $this->normalizeController($resource['controller'] ?? '');
                $groupPrefix = $this->normalizeSegment($resource['group_prefix'] ?? '');
                $routes      = isset($resource['routes']) && is_array($resource['routes']) ? $resource['routes'] : [];

                $normalizedRoutes = [];

                foreach ($routes as $route) {
                    $normalizedRoutes[] = [
                        'path'    => $this->normalizePath($route['path'] ?? ''),
                        'action'  => $this->normalizePath($route['action'] ?? ''),
                        'methods' => isset($route['methods']) && is_array($route['methods']) ? $route['methods'] : [],
                    ];
                }

                $definitionResources[] = [
                    'group_prefix' => $groupPrefix,
                    'controller'   => $controller,
                    'routes'       => $normalizedRoutes,
                ];
            }

            $definitions['versions'][$versionKey] = [
                'prefix'    => $versionPrefix,
                'resources' => $definitionResources,
            ];
        }

        return $definitions;
    }

    private function normalizeSegment($segment)
    {
        if (!is_string($segment)) {
            return '';
        }

        return trim($segment, '/');
    }

    private function normalizeController($controller)
    {
        if (!is_string($controller)) {
            return '';
        }

        return trim($controller, '/');
    }

    private function normalizePath($path)
    {
        if (!is_string($path)) {
            return '';
        }

        return trim($path, '/');
    }

    private function buildTarget($controller, $action)
    {
        if ($controller === '' || $action === '') {
            return '';
        }

        return $controller . '/' . $action;
    }
}
