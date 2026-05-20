<?php
//file: Views/Activity/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include helper functions
include BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Set up activity-specific helper functions
function getActivityIcon(string $eventType): string
{
    return match($eventType) {
        'login_attempt' => '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m0 4h14m-5 4v14a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6z"></path></svg>',
        'logout' => '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>',
        'create' => '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>',
        'update' => '<svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>',
        'delete' => '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>',
        'list_view' => '<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>',
        'detail_view' => '<svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>',
        'form_view' => '<svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
        default => '<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
    };
}

function getEntityTypeColor(string $entityType): string
{
    return match($entityType) {
        'project' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'task' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'user' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        'company' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        'sprint' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
        'milestone' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'auth' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'dashboard' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
    };
}

function formatActivityDescription(object $activity): string
{
    if (!empty($activity->description)) {
        return htmlspecialchars($activity->description);
    }

    // Fallback description generation
    $action = match($activity->event_type) {
        'login_attempt' => 'attempted to log in',
        'logout' => 'logged out',
        'create' => 'created',
        'update' => 'updated',
        'delete' => 'deleted',
        'list_view' => 'viewed list of',
        'detail_view' => 'viewed details of',
        'form_view' => 'accessed form for',
        default => 'performed ' . $activity->event_type . ' on'
    };

    // Use entity_name if available, otherwise extract from path
    if (!empty($activity->entity_name)) {
        $target = htmlspecialchars($activity->entity_name);
    } elseif (!empty($activity->entity_type)) {
        $target = htmlspecialchars($activity->entity_type);
    } else {
        // Extract target from path
        $pathParts = explode('/', trim($activity->path, '/'));
        $target = $pathParts[0] ?? 'resource';
        $target = ucfirst($target);
    }

    return $action . ' ' . $target;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $viewTitle ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header and Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Page Header with Breadcrumb -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div class="flex-1">
                <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
            </div>
        </div>

        <!-- Activity Stats Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-blue-100 dark:bg-blue-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-blue-500 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Activities</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100"><?= $stats['total_activities'] ?></div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-green-100 dark:bg-green-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-green-500 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Activities</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100"><?= $stats['today_activities'] ?></div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-yellow-100 dark:bg-yellow-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-yellow-500 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Recent Logins</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100"><?= $stats['recent_logins'] ?></div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-purple-100 dark:bg-purple-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-purple-500 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Users</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100"><?= $stats['active_users'] ?></div>
                </div>
            </div>
        </div>

        <!-- Activity Filters -->
        <?php include BASE_PATH . '/../src/Views/Activity/inc/filters.php'; ?>
        
        <!-- Activity Timeline -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Activity Timeline</h3>
                
                <?php if (empty($activities)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No activities found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No activities match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            <?php foreach ($activities as $index => $activity): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($index < count($activities) - 1): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                    <?= getActivityIcon($activity->event_type) ?>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-900 dark:text-gray-100">
                                                        <span class="font-medium">
                                                            <?= htmlspecialchars($activity->first_name . ' ' . $activity->last_name) ?>
                                                        </span>
                                                        <?= formatActivityDescription($activity) ?>
                                                        <?php
                                                        // Show entity type badge if available
                                                        $entityType = $activity->entity_type ?? null;
                                if (!$entityType && $activity->path) {
                                    // Extract entity type from path as fallback
                                    $pathParts = explode('/', trim($activity->path, '/'));
                                    $entityType = $pathParts[0] ?? null;
                                }
                                if ($entityType):
                                    ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getEntityTypeColor($entityType) ?> ml-2">
                                                                <?= htmlspecialchars(ucfirst($entityType)) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                                        <p class="flex items-center space-x-2">
                                                            <span><?= htmlspecialchars($activity->method) ?></span>
                                                            <span>•</span>
                                                            <span><?= htmlspecialchars($activity->path) ?></span>
                                                            <?php if ($activity->ip_address): ?>
                                                                <span>•</span>
                                                                <span><?= htmlspecialchars($activity->ip_address) ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    <time datetime="<?= $activity->created_at ?>">
                                                        <?= date('M j, Y g:i A', strtotime($activity->created_at)) ?>
                                                    </time>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php include BASE_PATH . '/../src/Views/Activity/inc/pagination.php'; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- JavaScript for Activity Filtering -->
    <script>
        // Auto-submit form on filter changes
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('activity-filters');
            if (filterForm) {
                const selects = filterForm.querySelectorAll('select');
                const inputs = filterForm.querySelectorAll('input[type="date"]');
                
                selects.forEach(select => {
                    select.addEventListener('change', () => filterForm.submit());
                });
                
                inputs.forEach(input => {
                    input.addEventListener('change', () => filterForm.submit());
                });
            }
        });
    </script>
</body>
</html>