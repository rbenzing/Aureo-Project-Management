<?php
//file: Views/TimeTracking/edit.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Utils\Time;

// Include form components (ViewHelpers.php is already loaded by BaseController::render())
require_once BASE_PATH . '/../src/Views/Layouts/FormComponents.php';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Time Entry - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header and Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>
        <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>

        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-medium dark:text-white">Edit Time Entry</h1>
            <a href="/time-tracking" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md text-sm">
                Back to Time Tracking
            </a>
        </div>

        <!-- Edit Form -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Time Entry Details</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Update the time entry information below.</p>
            </div>

            <form action="/time-tracking/update/<?= $timeEntry->id ?? '' ?>" method="POST" class="p-6">
                <?= renderCSRFToken() ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Task Selection -->
                    <div class="md:col-span-2">
                        <?= renderSelect([
                            'name' => 'task_id',
                            'label' => 'Task',
                            'value' => $timeEntry->task_id ?? '',
                            'options' => array_column($tasks ?? [], 'title', 'id'),
                            'empty_option' => 'Select a task...',
                            'required' => true,
                            'error' => $errors['task_id'] ?? '',
                        ]) ?>
                    </div>

                    <!-- Start Date and Time -->
                    <div>
                        <?= renderTextInput([
                            'name' => 'start_date',
                            'type' => 'date',
                            'label' => 'Start Date',
                            'value' => isset($timeEntry->start_time) ? date('Y-m-d', strtotime($timeEntry->start_time)) : '',
                            'required' => true,
                            'error' => $errors['start_date'] ?? '',
                        ]) ?>
                    </div>

                    <div>
                        <?= renderTextInput([
                            'name' => 'start_time',
                            'type' => 'time',
                            'label' => 'Start Time',
                            'value' => isset($timeEntry->start_time) ? date('H:i', strtotime($timeEntry->start_time)) : '',
                            'required' => true,
                            'error' => $errors['start_time'] ?? '',
                        ]) ?>
                    </div>

                    <!-- End Date and Time -->
                    <div>
                        <?= renderTextInput([
                            'name' => 'end_date',
                            'type' => 'date',
                            'label' => 'End Date',
                            'value' => isset($timeEntry->end_time) ? date('Y-m-d', strtotime($timeEntry->end_time)) : '',
                            'required' => true,
                            'error' => $errors['end_date'] ?? '',
                        ]) ?>
                    </div>

                    <div>
                        <?= renderTextInput([
                            'name' => 'end_time',
                            'type' => 'time',
                            'label' => 'End Time',
                            'value' => isset($timeEntry->end_time) ? date('H:i', strtotime($timeEntry->end_time)) : '',
                            'required' => true,
                            'error' => $errors['end_time'] ?? '',
                        ]) ?>
                    </div>

                    <!-- Duration (calculated automatically) -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Duration
                        </label>
                        <div class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md">
                            <span id="calculated-duration" class="text-sm text-gray-600 dark:text-gray-400">
                                <?= isset($timeEntry->duration) ? Time::formatSeconds($timeEntry->duration) : '0h 0m' ?>
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Duration is automatically calculated based on start and end times.
                        </p>
                    </div>

                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <?= renderTextarea([
                            'name' => 'notes',
                            'label' => 'Notes',
                            'value' => $timeEntry->notes ?? '',
                            'placeholder' => 'Add any notes about this time entry...',
                            'rows' => 3,
                            'error' => $errors['notes'] ?? '',
                        ]) ?>
                    </div>

                    <!-- Billable -->
                    <div class="md:col-span-2">
                        <?= renderCheckbox([
                            'name' => 'is_billable',
                            'label' => 'This time is billable',
                            'checked' => !empty($timeEntry->is_billable),
                            'help_text' => 'Check this if the time should be billed to the client.',
                        ]) ?>
                    </div>
                </div>

                <!-- Form Buttons -->
                <?= renderFormButtons([
                    'submit_text' => 'Update Time Entry',
                    'cancel_url' => '/time-tracking',
                    'additional_buttons' => [
                        [
                            'type' => 'button',
                            'text' => 'Delete Entry',
                            'class' => 'bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md',
                            'onclick' => 'deleteTimeEntry(' . ($timeEntry->id ?? 0) . ')',
                        ],
                    ],
                ]) ?>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        // Calculate duration when start/end times change
        function calculateDuration() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const startTime = document.querySelector('input[name="start_time"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const endTime = document.querySelector('input[name="end_time"]').value;
            
            if (startDate && startTime && endDate && endTime) {
                const start = new Date(startDate + 'T' + startTime);
                const end = new Date(endDate + 'T' + endTime);
                
                if (end > start) {
                    const diffMs = end - start;
                    const diffSeconds = Math.floor(diffMs / 1000);
                    const hours = Math.floor(diffSeconds / 3600);
                    const minutes = Math.floor((diffSeconds % 3600) / 60);
                    
                    document.getElementById('calculated-duration').textContent = hours + 'h ' + minutes + 'm';
                } else {
                    document.getElementById('calculated-duration').textContent = 'Invalid time range';
                }
            }
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const timeInputs = document.querySelectorAll('input[type="date"], input[type="time"]');
            timeInputs.forEach(input => {
                input.addEventListener('change', calculateDuration);
            });
        });

        // Delete time entry function
        function deleteTimeEntry(id) {
            if (confirm('Are you sure you want to delete this time entry? This action cannot be undone.')) {
                fetch('/time-tracking/delete/' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken || document.querySelector('input[name="csrf_token"]')?.value || ''
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '/time-tracking';
                    } else {
                        alert('Error deleting time entry: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting time entry');
                });
            }
        }
    </script>
</body>
</html>
