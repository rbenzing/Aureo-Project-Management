<?php
//file: Views/Projects/inc/table_projects.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Utils\Time;

?>
<!-- Project Overview (default view) -->
<div class="p-6">
    <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-6">
        <div class="flex-1">
            <?php if (isset($project->description) && !empty($project->description)): ?>
                <h3 class="text-base font-medium text-gray-900 dark:text-white mb-2">Description</h3>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    <?= nl2br(htmlspecialchars($project->description)) ?>
                </p>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No description available.</p>
            <?php endif; ?>
        </div>

        <div class="w-full md:w-64">
            <h3 class="text-base font-medium text-gray-900 dark:text-white mb-2">Project Details</h3>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-4">
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Owner:</span>
                        <span class="text-gray-900 dark:text-white ml-2">
                            <?= isset($project->owner_firstname) ? htmlspecialchars($project->owner_firstname . ' ' . $project->owner_lastname) : 'Not assigned' ?>
                        </span>
                    </div>

                    <?php if (isset($project->start_date) && !empty($project->start_date)): ?>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Start Date:</span>
                            <span class="text-gray-900 dark:text-white ml-2">
                                <?= date('M j, Y', strtotime($project->start_date)) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($project->end_date) && !empty($project->end_date)): ?>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">End Date:</span>
                            <span class="text-gray-900 dark:text-white ml-2">
                                <?= date('M j, Y', strtotime($project->end_date)) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Tasks:</span>
                        <span class="text-gray-900 dark:text-white ml-2">
                            <?= $totalTasks ?> (<?= $completedTasks ?> completed)
                        </span>
                    </div>

                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Total Time:</span>
                        <span class="text-gray-900 dark:text-white ml-2">
                            <?= Time::formatSeconds($totalTime) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>