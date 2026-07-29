<?php

// file: Core/Router.php
declare(strict_types=1);

namespace App\Core;

class Router
{
    /**
     * Store routes by HTTP method
     * @var array
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    /**
     * Store route patterns
     * @var array
     */
    private array $patterns = [
        ':id' => '([0-9]+)',
        ':task_id' => '([0-9]+)',
        ':time_entry_id' => '([0-9]+)',
        ':project_id' => '([0-9]+)',
        ':slug' => '([a-zA-Z0-9-]+)',
        ':page' => '([0-9]+)',
        ':any' => '(.*)',
    ];

    /**
     * Dependency Injection Container
     * @var \Psr\Container\ContainerInterface|null
     */
    private ?\Psr\Container\ContainerInterface $container = null;

    /**
     * Constructor - Now supports dependency injection
     *
     * @param \Psr\Container\ContainerInterface|null $container Optional DI container
     */
    public function __construct(?\Psr\Container\ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Add a route
     * @param string $method HTTP method
     * @param string $route Route pattern
     * @param array $params Route parameters
     */
    public function addRoute(string $method, string $route, array $params): void
    {
        // Normalize route
        $route = trim($route, '/');
        if (empty($route)) {
            $route = 'dashboard';
        }

        // Convert route patterns to regex
        $route = $this->convertRouteToRegex($route);

        // Store route
        $this->routes[strtoupper($method)][$route] = $params;
    }

    /**
     * Convert route patterns to regex
     * @param string $route
     * @return string
     */
    private function convertRouteToRegex(string $route): string
    {
        if (strpos($route, ':') !== false) {
            foreach ($this->patterns as $pattern => $replacement) {
                $route = str_replace($pattern, $replacement, $route);
            }
        }

        return $route;
    }

    /**
     * Match current route
     * @param string $method HTTP method
     * @param string $uri Current URI
     * @return array|false
     */
    private function matchRoute(string $method, string $uri): array|false
    {
        $uri = trim($uri, '/');
        if (empty($uri)) {
            $uri = 'dashboard';
        }


        foreach ($this->routes[$method] as $route => $params) {
            if (preg_match('#^' . $route . '$#', $uri, $matches)) {
                array_shift($matches); // Remove full match

                return ['params' => $params, 'matches' => $matches];
            }
        }

        return false;
    }

    /**
     * Dispatch route
     * @param string $requestMethod HTTP method
     * @param array $urlSegments URL segments
     * @throws \Exception
     */
    public function dispatch(string $requestMethod, array $urlSegments): void
    {
        // Build URI from segments
        $uri = implode('/', $urlSegments);

        // Match route
        $match = $this->matchRoute($requestMethod, $uri);

        if (!$match) {
            throw new \Exception('Page not found', 404);
        }

        // Extract controller and action
        $params = $match['params'];
        $matches = $match['matches'];

        $controllerName = ucfirst($params['controller']) . 'Controller';
        $actionName = $params['action'] ?? 'index';

        // Build controller class name
        $controllerClass = '\\App\\Controllers\\' . $controllerName;

        if (!class_exists($controllerClass)) {
            throw new \Exception('Controller not found', 404);
        }

        // Create controller instance - use DI container if available
        if ($this->container && $this->container->has($controllerClass)) {
            $controller = $this->container->get($controllerClass);
        } else {
            $controller = new $controllerClass();
        }

        if (!method_exists($controller, $actionName)) {
            throw new \Exception('Action not found', 404);
        }

        // Build request data
        $requestData = [];

        // Map route parameters to names if defined
        if (isset($params['params']) && !empty($matches)) {
            $paramNames = $params['params'];

            foreach ($matches as $index => $value) {
                if (isset($paramNames[$index])) {
                    $requestData[$paramNames[$index]] = $value;
                }
            }
        }

        // For POST requests, merge POST data with URL parameters
        if ($requestMethod === 'POST') {
            $requestData = array_merge($requestData, $_POST);
        }

        // For GET requests, merge GET data with URL parameters
        if ($requestMethod === 'GET') {
            $requestData = array_merge($requestData, $_GET);
        }

        // Call controller action
        call_user_func([$controller, $actionName], $requestMethod, $requestData);
    }

    /**
     * Quick methods to add routes
     */
    public function get(string $route, array $params): void
    {
        $this->addRoute('GET', $route, $params);
    }

    public function post(string $route, array $params): void
    {
        $this->addRoute('POST', $route, $params);
    }

    public function put(string $route, array $params): void
    {
        $this->addRoute('PUT', $route, $params);
    }

    public function delete(string $route, array $params): void
    {
        $this->addRoute('DELETE', $route, $params);
    }
}
