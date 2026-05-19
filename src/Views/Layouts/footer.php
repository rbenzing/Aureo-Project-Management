<?php
//file: Views/Layouts/footer.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Services\SettingsService;

// Get current year using configured timezone
$settingsService = SettingsService::getInstance();
$timezone = $settingsService->getDefaultTimezone();

try {
    $currentYear = (new DateTime('now', new DateTimeZone($timezone)))->format('Y');
} catch (Exception $e) {
    $currentYear = date('Y'); // Fallback
}
?>

<footer class="bg-gray-800 text-white py-4 mt-auto">
    <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 text-center text-sm">
        &copy; <?php echo $currentYear; ?> <?php echo htmlspecialchars(Config::get('company_name')); ?>, All rights reserved.
    </div>
</footer>

<!-- Include Floating Timer -->
<?php include BASE_PATH . '/../src/Views/Layouts/FloatingTimer.php'; ?>

<!-- Include Command Palette -->
<?php include BASE_PATH . '/../src/Views/Layouts/CommandPalette.php'; ?>

<script type="text/javascript" src="/assets/js/scripts.js"></script>
<script src="/assets/js/command-palette.js" defer></script>

<!-- Ensure hamburger menu works on all pages - robust fallback initialization -->
<script>
// Robust hamburger menu initialization - ensures it works on ALL pages
(function() {
    'use strict';

    let hamburgerInitialized = false;

    function initHamburgerMenu() {
        if (hamburgerInitialized) return;

        const sidebar = document.getElementById('sidebar');
        const toggleButton = document.getElementById('sidebar-toggle');
        const closeButton = document.getElementById('sidebar-close');

        if (!sidebar || !toggleButton) {
            console.warn('Hamburger menu elements not found');
            return;
        }

        // Mark as initialized to prevent duplicate initialization
        hamburgerInitialized = true;

        // Clear any existing event listeners by replacing the button
        const newToggleButton = toggleButton.cloneNode(true);
        toggleButton.parentNode.replaceChild(newToggleButton, toggleButton);

        // Add click handler for toggle button
        newToggleButton.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
            console.log('Hamburger menu toggled');
        });

        // Handle close button if it exists
        if (closeButton) {
            const newCloseButton = closeButton.cloneNode(true);
            closeButton.parentNode.replaceChild(newCloseButton, closeButton);

            newCloseButton.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                sidebar.classList.add('-translate-x-full');
            });
        }

        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (!sidebar.contains(event.target) &&
                !newToggleButton.contains(event.target) &&
                !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Prevent sidebar from closing when clicking inside it
        sidebar.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        console.log('Hamburger menu initialized successfully');
    }

    // Try multiple initialization strategies
    function attemptInitialization() {
        // Strategy 1: Immediate if DOM is ready
        if (document.readyState === 'complete') {
            initHamburgerMenu();
            return;
        }

        // Strategy 2: On DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initHamburgerMenu, 100);
            });
        } else {
            setTimeout(initHamburgerMenu, 100);
        }

        // Strategy 3: Fallback with longer delay
        setTimeout(function() {
            if (!hamburgerInitialized) {
                initHamburgerMenu();
            }
        }, 1000);
    }

    // Start initialization attempts
    attemptInitialization();
})();
</script>

<!-- Avatar Dropdown - Load last to ensure it works on all pages -->
<script>
// Ensure avatar dropdown works on all pages - runs after all other scripts
(function() {
    function initAvatarDropdown() {
        const avatarDropdown = document.querySelector('header .dropdown');
        if (!avatarDropdown) return;

        const button = avatarDropdown.querySelector('button');
        const menu = avatarDropdown.querySelector('.dropdown-menu');

        if (!button || !menu) return;

        // Clear any existing event listeners
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);

        // Add click handler
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu:not(.hidden)').forEach(otherMenu => {
                if (otherMenu !== menu) {
                    otherMenu.classList.add('hidden');
                }
            });

            menu.classList.toggle('hidden');
            newButton.setAttribute('aria-expanded', !menu.classList.contains('hidden'));
        });

        // Close on outside click
        document.addEventListener('click', function(event) {
            if (!avatarDropdown.contains(event.target)) {
                menu.classList.add('hidden');
                newButton.setAttribute('aria-expanded', 'false');
            }
        });

        // Close on escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
                newButton.setAttribute('aria-expanded', 'false');
                newButton.focus();
            }
        });
    }

    // Initialize immediately if DOM is ready, otherwise wait
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initAvatarDropdown, 200);
        });
    } else {
        setTimeout(initAvatarDropdown, 200);
    }
})();
</script>

<!-- Common JavaScript Functions -->
<script>
// Delete entity function
function deleteEntity(entityType, id) {
    if (confirm('Are you sure you want to delete this ' + entityType + '?')) {
        // Get CSRF token from meta tag or session
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                         '<?= $csrfToken ?? '' ?>';

        fetch('/' + entityType + '/delete/' + id, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting ' + entityType + ': ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting ' + entityType);
        });
    }
}


</script>