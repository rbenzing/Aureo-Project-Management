<?php
//file: Views/Layouts/FloatingTimer.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Include view helpers for permission functions
require_once BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Only show if there's an active timer AND user has time tracking permissions
if (empty($_SESSION['active_timer']) ||
    (!hasUserPermission('view_time_tracking') && !hasUserPermission('create_time_tracking'))) {
    return;
}

$activeTimer = $_SESSION['active_timer'];
$duration = time() - $activeTimer['start_time'];
$taskTitle = $activeTimer['task_title'] ?? 'Unknown Task';
$projectName = $activeTimer['project_name'] ?? 'Unknown Project';
?>

<!-- Floating Timer Widget -->
<div id="floating-timer" class="fixed bottom-4 left-4 z-50 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 min-w-80 max-w-96" style="position: fixed; bottom: 16px; left: 16px; z-index: 9999; min-width: 320px; max-width: 384px;">
    <!-- Timer Header -->
    <div class="bg-indigo-600 text-white px-4 py-2 rounded-t-lg flex items-center justify-between">
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm font-medium">Timer Running</span>
        </div>
        <button id="minimize-timer" class="text-white hover:text-gray-200 focus:outline-none" title="Minimize">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </button>
    </div>

    <!-- Timer Content -->
    <div id="timer-content" class="p-4">
        <!-- Task Information -->
        <div class="mb-3">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" title="<?= htmlspecialchars($taskTitle) ?>">
                <?= htmlspecialchars(strlen($taskTitle) > 35 ? substr($taskTitle, 0, 35) . '...' : $taskTitle) ?>
            </h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 truncate" title="<?= htmlspecialchars($projectName) ?>">
                <?= htmlspecialchars($projectName) ?>
            </p>
        </div>

        <!-- Timer Display -->
        <div class="text-center mb-4">
            <div id="floating-timer-display" class="text-2xl font-mono font-bold text-indigo-600 dark:text-indigo-400" data-start-time="<?= $activeTimer['start_time'] ?>">
                <?= gmdate('H:i:s', $duration) ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex space-x-2">
            <form action="/tasks/stop-timer/<?= $activeTimer['task_id'] ?>" method="POST" class="flex-1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-3 rounded-md text-sm font-medium flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                    </svg>
                    Stop Timer
                </button>
            </form>
            <a href="/tasks/view/<?= $activeTimer['task_id'] ?>" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-3 rounded-md text-sm font-medium flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                View
            </a>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const floatingTimer = document.getElementById('floating-timer');
    const minimizeButton = document.getElementById('minimize-timer');
    const floatingTimerDisplay = document.getElementById('floating-timer-display');

    if (!floatingTimer || !floatingTimerDisplay) {
        return;
    }

    // Check if timer should be minimized (hidden) based on localStorage
    const isTimerMinimized = localStorage.getItem('timerMinimized') === 'true';
    if (isTimerMinimized) {
        floatingTimer.style.display = 'none';
    }

    // Ensure timer starts in correct position (reset any transforms)
    floatingTimer.style.transform = 'translate3d(0px, 0px, 0)';

    const startTime = parseInt(floatingTimerDisplay.getAttribute('data-start-time'));

    // Update timer displays
    function updateTimerDisplays() {
        const now = Math.floor(Date.now() / 1000);
        const elapsed = now - startTime;

        const hours = Math.floor(elapsed / 3600);
        const minutes = Math.floor((elapsed % 3600) / 60);
        const seconds = elapsed % 60;

        // Format time based on settings (default to H:M:S for timer)
        const timeString =
            (hours < 10 ? '0' + hours : hours) + ':' +
            (minutes < 10 ? '0' + minutes : minutes) + ':' +
            (seconds < 10 ? '0' + seconds : seconds);

        floatingTimerDisplay.textContent = timeString;
    }

    // Minimize functionality - hide timer and store state
    if (minimizeButton) {
        minimizeButton.addEventListener('click', function() {
            floatingTimer.style.display = 'none';
            localStorage.setItem('timerMinimized', 'true');
        });
    }

    // Global function to show timer (called from header)
    window.showFloatingTimer = function() {
        floatingTimer.style.display = 'block';
        localStorage.setItem('timerMinimized', 'false');
    };

    // Update timer every second
    updateTimerDisplays(); // Initial update
    setInterval(updateTimerDisplays, 1000);

    // Make timer draggable (optional enhancement)
    let isDragging = false;
    let currentX = 0;
    let currentY = 0;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;

    function dragStart(e) {
        if (e.target.closest('button') || e.target.closest('form') || e.target.closest('a')) {
            return;
        }

        if (e.type === "touchstart") {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }

        if (e.target === floatingTimer || floatingTimer.contains(e.target)) {
            isDragging = true;
        }
    }

    function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
    }

    function drag(e) {
        if (isDragging) {
            e.preventDefault();

            if (e.type === "touchmove") {
                currentX = e.touches[0].clientX - initialX;
                currentY = e.touches[0].clientY - initialY;
            } else {
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
            }

            xOffset = currentX;
            yOffset = currentY;

            floatingTimer.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
        }
    }

    // Add event listeners for dragging
    floatingTimer.addEventListener("mousedown", dragStart, false);
    document.addEventListener("mouseup", dragEnd, false);
    document.addEventListener("mousemove", drag, false);

    // Touch events for mobile
    floatingTimer.addEventListener("touchstart", dragStart, false);
    document.addEventListener("touchend", dragEnd, false);
    document.addEventListener("touchmove", drag, false);
});
</script>
