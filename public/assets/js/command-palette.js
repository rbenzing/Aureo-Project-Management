/**
 * Command Palette — Ctrl+K global search
 */
(function () {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  let isOpen        = false;
  let activeIndex   = -1;
  let currentQuery  = '';
  let debounceTimer = null;
  let results       = [];
  const DEBOUNCE_MS = 200;
  const MIN_CHARS   = 1;
  let activeType    = ''; // '' = All

  // ── Elements ───────────────────────────────────────────────────────────────
  const overlay   = document.getElementById('command-palette-overlay');
  const input     = document.getElementById('cmd-search-input');
  const resultBox = document.getElementById('cmd-results');
  const loading   = document.getElementById('cmd-loading');
  const empty     = document.getElementById('cmd-empty');
  const openBtn   = document.getElementById('cmd-open-btn');
  const chips     = Array.from(document.querySelectorAll('.cmd-chip'));

  if (!overlay || !input || !resultBox) return; // bail if partial not present

  // ── Open / Close ───────────────────────────────────────────────────────────
  function open() {
    isOpen = true;
    overlay.classList.remove('hidden');
    input.value = '';
    input.setAttribute('aria-expanded', 'true');
    activeIndex = -1;
    results = [];
    clearResults();
    setActiveChip('');
    requestAnimationFrame(() => input.focus());
  }

  function close() {
    isOpen = false;
    overlay.classList.add('hidden');
    input.setAttribute('aria-expanded', 'false');
    activeIndex = -1;
    results = [];
    clearResults();
    input.value = '';
  }

  function toggle() {
    isOpen ? close() : open();
  }

  // ── Result rendering ───────────────────────────────────────────────────────
  const ICONS = {
    task:      '✓',
    project:   '📁',
    company:   '🏢',
    user:      '👤',
    sprint:    '⚡',
    milestone: '🏁',
  };

  const TYPE_LABELS = {
    task:      'Task',
    project:   'Project',
    company:   'Company',
    user:      'User',
    sprint:    'Sprint',
    milestone: 'Milestone',
  };

  function clearResults() {
    // Remove all result items (keep #cmd-empty in place)
    Array.from(resultBox.querySelectorAll('[role="option"]')).forEach(el => el.remove());
    empty.classList.add('hidden');
  }

  function renderResults(items) {
    clearResults();
    results = items;
    activeIndex = -1;

    if (items.length === 0) {
      empty.classList.remove('hidden');
      return;
    }

    items.forEach((item, idx) => {
      const el = document.createElement('div');
      el.className = [
        'flex items-center gap-3 px-4 py-3 cursor-pointer select-none',
        'text-sm text-gray-900 dark:text-gray-100',
        'hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
        'border-b border-gray-100 dark:border-gray-800 last:border-0',
        'transition-colors',
      ].join(' ');
      el.setAttribute('role', 'option');
      el.setAttribute('aria-selected', 'false');
      el.dataset.index = idx;

      const icon = document.createElement('span');
      icon.className = 'flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-md text-base bg-gray-100 dark:bg-gray-800';
      icon.textContent = ICONS[item.entity_type] || '📄';

      const text = document.createElement('div');
      text.className = 'flex-1 min-w-0';

      const title = document.createElement('div');
      title.className = 'font-medium truncate';
      title.textContent = item.title;

      const meta = document.createElement('div');
      meta.className = 'text-xs text-gray-500 dark:text-gray-400 truncate';
      meta.textContent = item.snippet || (TYPE_LABELS[item.entity_type] || item.entity_type);

      text.appendChild(title);
      if (item.snippet) text.appendChild(meta);

      const badge = document.createElement('span');
      badge.className = 'flex-shrink-0 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400';
      badge.textContent = TYPE_LABELS[item.entity_type] || item.entity_type;

      el.appendChild(icon);
      el.appendChild(text);
      el.appendChild(badge);

      el.addEventListener('click', () => selectResult(idx));
      el.addEventListener('mouseenter', () => setActive(idx));

      resultBox.appendChild(el);
    });
  }

  function renderRecentQueries(queries) {
    clearResults();
    results = [];
    if (!queries || queries.length === 0) return;

    const header = document.createElement('div');
    header.className = 'px-4 py-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider';
    header.textContent = 'Recent searches';
    header.setAttribute('role', 'presentation');
    resultBox.insertBefore(header, empty);

    queries.forEach((q) => {
      const el = document.createElement('div');
      el.className = [
        'flex items-center gap-3 px-4 py-2.5 cursor-pointer select-none',
        'text-sm text-gray-700 dark:text-gray-300',
        'hover:bg-gray-50 dark:hover:bg-gray-800',
        'border-b border-gray-100 dark:border-gray-800 last:border-0',
        'transition-colors',
      ].join(' ');
      el.setAttribute('role', 'option');

      const icon = document.createElement('span');
      icon.className = 'text-gray-400';
      icon.textContent = '🕐';

      const text = document.createElement('span');
      text.className = 'flex-1 truncate';
      text.textContent = q.query;

      el.appendChild(icon);
      el.appendChild(text);
      el.addEventListener('click', () => {
        input.value = q.query;
        doSearch(q.query);
      });
      resultBox.appendChild(el);
    });
  }

  // ── Active item navigation ─────────────────────────────────────────────────
  function setActive(idx) {
    const items = resultBox.querySelectorAll('[role="option"][data-index]');
    items.forEach(el => {
      el.classList.remove('bg-indigo-50', 'dark:bg-indigo-900/20');
      el.setAttribute('aria-selected', 'false');
    });
    if (idx >= 0 && idx < items.length) {
      activeIndex = idx;
      items[idx].classList.add('bg-indigo-50', 'dark:bg-indigo-900/20');
      items[idx].setAttribute('aria-selected', 'true');
      items[idx].scrollIntoView({ block: 'nearest' });
    }
  }

  function selectResult(idx) {
    if (results[idx]) {
      recordClick(currentQuery, idx);
      window.location.href = results[idx].url;
    }
  }

  // ── Scope chips ────────────────────────────────────────────────────────────
  function setActiveChip(type) {
    activeType = type;
    chips.forEach((chip) => {
      const on = chip.dataset.type === type;
      chip.setAttribute('aria-pressed', on ? 'true' : 'false');
      chip.classList.toggle('bg-indigo-600', on);
      chip.classList.toggle('text-white', on);
      chip.classList.toggle('border-indigo-600', on);
      chip.classList.toggle('bg-gray-100', !on);
      chip.classList.toggle('dark:bg-gray-800', !on);
      chip.classList.toggle('text-gray-600', !on);
      chip.classList.toggle('dark:text-gray-300', !on);
      chip.classList.toggle('border-gray-300', !on);
      chip.classList.toggle('dark:border-gray-600', !on);
      // Only inactive chips get a hover affordance; active (indigo) chips must not.
      chip.classList.toggle('hover:bg-gray-200', !on);
      chip.classList.toggle('dark:hover:bg-gray-700', !on);
    });

    const q = input.value.trim();
    if (q.length >= MIN_CHARS) {
      doSearch(q);
    } else {
      clearResults();
      loadRecentQueries();
    }
  }

  // ── API calls ──────────────────────────────────────────────────────────────
  async function doSearch(query) {
    currentQuery = query;
    clearResults();
    loading.classList.remove('hidden');

    try {
      let url = `/api/search?q=${encodeURIComponent(query)}&limit=30`;
      if (activeType) url += `&types[]=${encodeURIComponent(activeType)}`;
      const res = await fetch(url);
      const json = await res.json();
      loading.classList.add('hidden');
      if (json.success && json.data) {
        renderResults(json.data.results || []);
      }
    } catch {
      loading.classList.add('hidden');
    }
  }

  async function loadRecentQueries() {
    try {
      const res = await fetch('/api/search/recent?limit=8');
      const json = await res.json();
      if (json.success && json.data) {
        renderRecentQueries(json.data.queries || []);
      }
    } catch {
      // silent fail
    }
  }

  function recordClick(query, position) {
    const csrfToken = window.csrfToken || '';
    fetch('/api/search/click', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ query, position }),
    }).catch(() => {});
  }

  // ── Event listeners ────────────────────────────────────────────────────────
  // Open via button
  if (openBtn) {
    openBtn.addEventListener('click', open);
  }

  // Scope chip clicks
  chips.forEach((chip) => {
    chip.addEventListener('click', () => setActiveChip(chip.dataset.type || ''));
  });

  // Ctrl+K / Cmd+K
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      toggle();
      return;
    }

    if (!isOpen) return;

    const items = resultBox.querySelectorAll('[role="option"][data-index]');
    const count = items.length;

    switch (e.key) {
      case 'Escape':
        e.preventDefault();
        close();
        break;

      case 'ArrowDown':
        e.preventDefault();
        setActive(Math.min(activeIndex + 1, count - 1));
        break;

      case 'ArrowUp':
        e.preventDefault();
        setActive(Math.max(activeIndex - 1, 0));
        break;

      case 'Enter':
        e.preventDefault();
        if (activeIndex >= 0) {
          selectResult(activeIndex);
        }
        break;
    }
  });

  // Close on backdrop click
  document.getElementById('cmd-backdrop')?.addEventListener('click', close);

  // Search input
  input.addEventListener('input', function () {
    const q = this.value.trim();
    clearTimeout(debounceTimer);

    if (q.length < MIN_CHARS) {
      clearResults();
      loadRecentQueries();
      return;
    }

    debounceTimer = setTimeout(() => doSearch(q), DEBOUNCE_MS);
  });

})();
