<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

/**
 * Dependency Injection Container Configuration
 *
 * This file configures the PHP-DI container with all application services,
 * defining how dependencies should be resolved and injected.
 */

$containerBuilder = new ContainerBuilder();

// Enable compilation in production for better performance
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

$containerBuilder->addDefinitions([
    // Core Services
    Database::class => function () {
        // Use factory method for clean DI instantiation
        return Database::create();
    },

    // Logger Service
    LoggerService::class => function (ContainerInterface $c) {
        return new LoggerService();
    },

    // Settings Service (autowired - Setting model will be instantiated automatically)
    SettingsService::class => function (ContainerInterface $c) {
        return new SettingsService();
    },

    // Security Service
    SecurityService::class => function (ContainerInterface $c) {
        return new SecurityService(
            $c->get(SettingsService::class),
            $c->get(Database::class)
        );
    },

    // Middleware
    \App\Middleware\AuthMiddleware::class => function (ContainerInterface $c) {
        return new \App\Middleware\AuthMiddleware();
    },

    \App\Middleware\CsrfMiddleware::class => function (ContainerInterface $c) {
        return new \App\Middleware\CsrfMiddleware();
    },

    \App\Middleware\SessionMiddleware::class => function (ContainerInterface $c) {
        return new \App\Middleware\SessionMiddleware();
    },

    \App\Middleware\ActivityMiddleware::class => function (ContainerInterface $c) {
        return new \App\Middleware\ActivityMiddleware();
    },

    // Models
    \App\Models\User::class => function (ContainerInterface $c) {
        return new \App\Models\User();
    },

    \App\Models\Project::class => function (ContainerInterface $c) {
        return new \App\Models\Project();
    },

    \App\Models\Task::class => function (ContainerInterface $c) {
        return new \App\Models\Task();
    },

    \App\Models\Milestone::class => function (ContainerInterface $c) {
        return new \App\Models\Milestone();
    },

    \App\Models\Sprint::class => function (ContainerInterface $c) {
        return new \App\Models\Sprint();
    },

    \App\Models\Company::class => function (ContainerInterface $c) {
        return new \App\Models\Company();
    },

    \App\Models\Role::class => function (ContainerInterface $c) {
        return new \App\Models\Role();
    },

    \App\Models\Permission::class => function (ContainerInterface $c) {
        return new \App\Models\Permission();
    },

    \App\Models\Template::class => function (ContainerInterface $c) {
        return new \App\Models\Template();
    },

    \App\Models\Setting::class => function (ContainerInterface $c) {
        return new \App\Models\Setting();
    },

    \App\Models\Favorite::class => function (ContainerInterface $c) {
        return new \App\Models\Favorite();
    },

    // Controllers
    \App\Controllers\AuthController::class => function (ContainerInterface $c) {
        return new \App\Controllers\AuthController(
            $c->get(\App\Middleware\AuthMiddleware::class),
            $c->get(\App\Models\User::class),
            $c->get(SecurityService::class)
        );
    },

    \App\Controllers\DashboardController::class => function (ContainerInterface $c) {
        return new \App\Controllers\DashboardController(
            $c->get(\App\Middleware\AuthMiddleware::class),
            $c->get(\App\Models\User::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\Task::class),
            $c->get(\App\Models\Milestone::class),
            $c->get(\App\Models\Sprint::class)
        );
    },

    \App\Controllers\TaskController::class => function (ContainerInterface $c) {
        return new \App\Controllers\TaskController(
            $c->get(\App\Models\Task::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\User::class),
            $c->get(\App\Models\Sprint::class),
            $c->get(\App\Models\Template::class)
        );
    },

    \App\Controllers\ProjectController::class => function (ContainerInterface $c) {
        return new \App\Controllers\ProjectController(
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\Task::class),
            $c->get(\App\Models\Company::class),
            $c->get(\App\Models\User::class),
            $c->get(\App\Models\Template::class)
        );
    },

    \App\Controllers\SprintController::class => function (ContainerInterface $c) {
        return new \App\Controllers\SprintController(
            $c->get(\App\Models\Sprint::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\Task::class),
            $c->get(\App\Models\Template::class)
        );
    },

    \App\Controllers\MilestoneController::class => function (ContainerInterface $c) {
        return new \App\Controllers\MilestoneController(
            $c->get(\App\Models\Milestone::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\Template::class)
        );
    },

    \App\Controllers\UserController::class => function (ContainerInterface $c) {
        return new \App\Controllers\UserController(
            $c->get(\App\Models\User::class),
            $c->get(\App\Models\Company::class),
            $c->get(\App\Models\Role::class)
        );
    },

    \App\Controllers\CompanyController::class => function (ContainerInterface $c) {
        return new \App\Controllers\CompanyController(
            $c->get(\App\Models\Company::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\User::class)
        );
    },

    \App\Controllers\RoleController::class => function (ContainerInterface $c) {
        return new \App\Controllers\RoleController(
            $c->get(\App\Models\Role::class),
            $c->get(\App\Models\Permission::class)
        );
    },

    \App\Controllers\TemplateController::class => function (ContainerInterface $c) {
        return new \App\Controllers\TemplateController(
            $c->get(\App\Models\Template::class),
            $c->get(\App\Models\Company::class)
        );
    },

    \App\Controllers\SettingsController::class => function (ContainerInterface $c) {
        return new \App\Controllers\SettingsController(
            $c->get(\App\Models\Setting::class)
        );
    },

    \App\Controllers\ActivityController::class => function (ContainerInterface $c) {
        return new \App\Controllers\ActivityController(
            $c->get(\App\Models\User::class)
        );
    },

    \App\Controllers\FavoritesController::class => function (ContainerInterface $c) {
        return new \App\Controllers\FavoritesController(
            $c->get(\App\Models\Favorite::class)
        );
    },

    \App\Controllers\TimeTrackingController::class => function (ContainerInterface $c) {
        return new \App\Controllers\TimeTrackingController(
            $c->get(\App\Models\Task::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\User::class)
        );
    },

    \App\Controllers\SprintTemplateController::class => function (ContainerInterface $c) {
        return new \App\Controllers\SprintTemplateController(
            $c->get(\App\Models\Template::class),
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\Company::class)
        );
    },

    // Services
    \App\Services\TaskService::class => function (ContainerInterface $c) {
        return new \App\Services\TaskService(
            $c->get(\App\Models\Task::class),
            $c->get(\App\Models\User::class),
            $c->get(\App\Services\LoggerService::class)
        );
    },

    \App\Services\ProjectService::class => function (ContainerInterface $c) {
        return new \App\Services\ProjectService(
            $c->get(\App\Models\Project::class),
            $c->get(\App\Models\User::class),
            $c->get(\App\Models\Task::class),
            $c->get(\App\Services\LoggerService::class)
        );
    },

    // Repositories
    \App\Repositories\TaskRepository::class => function (ContainerInterface $c) {
        return new \App\Repositories\TaskRepository(
            $c->get(\App\Models\Task::class)
        );
    },

    \App\Repositories\ProjectRepository::class => function (ContainerInterface $c) {
        return new \App\Repositories\ProjectRepository(
            $c->get(\App\Models\Project::class)
        );
    },

    \App\Repositories\UserRepository::class => function (ContainerInterface $c) {
        return new \App\Repositories\UserRepository(
            $c->get(\App\Models\User::class)
        );
    },

    \App\Repositories\SprintRepository::class => function (ContainerInterface $c) {
        return new \App\Repositories\SprintRepository(
            $c->get(\App\Models\Sprint::class)
        );
    },

    // Search
    \App\Models\SearchIndex::class => function (ContainerInterface $c) {
        return new \App\Models\SearchIndex();
    },

    \App\Repositories\SearchRepository::class => function (ContainerInterface $c) {
        return new \App\Repositories\SearchRepository(
            $c->get(\App\Models\SearchIndex::class)
        );
    },

    \App\Services\SearchService::class => function (ContainerInterface $c) {
        return new \App\Services\SearchService(
            $c->get(\App\Repositories\SearchRepository::class)
        );
    },

    \App\Controllers\SearchController::class => function (ContainerInterface $c) {
        return new \App\Controllers\SearchController(
            $c->get(\App\Services\SearchService::class)
        );
    },
]);

// Build and return the container
return $containerBuilder->build();
