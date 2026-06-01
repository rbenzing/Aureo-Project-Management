<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
?>
<!-- Command Palette Overlay -->
<div id="command-palette-overlay"
     class="fixed inset-0 z-50 hidden"
     role="dialog"
     aria-modal="true"
     aria-label="Command palette"
     aria-labelledby="cmd-palette-label">

    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="cmd-backdrop"></div>

    <!-- Modal Panel -->
    <div class="relative flex items-start justify-center pt-[10vh] px-4">
        <div id="command-palette"
             class="w-full max-w-[820px] bg-white dark:bg-gray-900 rounded-xl shadow-2xl
                    ring-1 ring-black/10 dark:ring-white/10 overflow-hidden">

            <!-- Search Input -->
            <div class="flex items-center px-4 border-b border-gray-200 dark:border-gray-700">
                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       id="cmd-search-input"
                       class="flex-1 px-3 py-4 text-base text-gray-900 dark:text-gray-100
                              bg-transparent border-0 outline-none placeholder-gray-400
                              dark:placeholder-gray-500"
                       placeholder="Search tasks, projects, users…"
                       autocomplete="off"
                       spellcheck="false"
                       aria-labelledby="cmd-palette-label"
                       aria-autocomplete="list"
                       aria-controls="cmd-results"
                       role="combobox"
                       aria-expanded="false">
                <div id="cmd-loading" class="hidden">
                    <svg class="animate-spin w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <kbd class="ml-2 hidden sm:inline-flex items-center px-2 py-1 text-xs
                            font-medium text-gray-500 dark:text-gray-400
                            bg-gray-100 dark:bg-gray-800 rounded border
                            border-gray-300 dark:border-gray-600">ESC</kbd>
            </div>

            <!-- Scope filter chips -->
            <div id="cmd-scope-chips"
                 class="flex items-center gap-2 px-4 py-2 overflow-x-auto
                        border-b border-gray-200 dark:border-gray-700
                        text-xs"
                 role="group"
                 aria-label="Filter by type">
                <?php
                $cmdChips = [
                    '' => 'All',
                    'task' => 'Tasks',
                    'project' => 'Projects',
                    'company' => 'Companies',
                    'user' => 'Users',
                    'sprint' => 'Sprints',
                    'milestone' => 'Milestones',
                ];
foreach ($cmdChips as $type => $label):
    $isAll = $type === '';
    ?>
                <button type="button"
                        class="cmd-chip flex-shrink-0 px-3 py-1 rounded-full border transition-colors
                               <?= $isAll
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-700' ?>"
                        data-type="<?= htmlspecialchars($type) ?>"
                        aria-pressed="<?= $isAll ? 'true' : 'false' ?>">
                    <?= htmlspecialchars($label) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Results -->
            <div id="cmd-results"
                 class="max-h-[60vh] overflow-y-auto overscroll-contain"
                 role="listbox"
                 aria-label="Search results">

                <!-- Empty state -->
                <div id="cmd-empty" class="hidden py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto mb-3 w-10 h-10 text-gray-300 dark:text-gray-600" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    No results found
                </div>

                <!-- Results are injected here by command-palette.js -->
            </div>

            <!-- Footer hints -->
            <div class="flex items-center justify-between px-4 py-2 border-t
                        border-gray-200 dark:border-gray-700
                        text-xs text-gray-400 dark:text-gray-500">
                <span id="cmd-palette-label" class="sr-only">Command palette</span>
                <span class="flex items-center gap-3">
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600
                                    bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-mono">↑↓</kbd>
                        navigate
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600
                                    bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-mono">↵</kbd>
                        open
                    </span>
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600
                                bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-mono">ESC</kbd>
                    to close
                </span>
            </div>
        </div>
    </div>
</div>
