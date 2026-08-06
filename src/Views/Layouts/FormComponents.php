<?php

//file: Views/Layouts/FormComponents.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

/**
 * Standardized form components for consistent UI across all forms
 */

/**
 * Render a text input field
 */
function renderTextInput(array $options): string
{
    $defaults = [
        'type' => 'text',
        'required' => false,
        'disabled' => false,
        'placeholder' => '',
        'value' => '',
        'class' => '',
        'help_text' => '',
        'error' => '',
        'icon' => '', // SVG path for icon
        'min' => '',
        'max' => '',
        'step' => '',
    ];

    $options = array_merge($defaults, $options);

    // Adjust padding based on whether icon is present
    $paddingClass = $options['icon'] ? 'pl-10 pr-3 py-2' : 'px-3 py-2';
    $baseClasses = 'w-full ' . $paddingClass . ' border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm';
    $errorClasses = $options['error'] ? 'border-red-300 dark:border-red-600' : 'border-gray-300 dark:border-gray-600';
    $disabledClasses = $options['disabled'] ? 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed' : '';

    $classes = trim($baseClasses . ' ' . $errorClasses . ' ' . $disabledClasses . ' ' . $options['class']);

    $html = '<div class="mb-4">';

    // Label
    if (!empty($options['label'])) {
        $html .= '<label for="' . htmlspecialchars((string)$options['name']) . '" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">';
        $html .= $options['label']; // Don't escape label as it may contain HTML like <span>
        if ($options['required']) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }

    // Input wrapper (for icon positioning)
    if ($options['icon']) {
        $html .= '<div class="relative rounded-md shadow-sm">';

        // Icon
        $html .= '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
        $html .= '<svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
        $html .= $options['icon'];
        $html .= '</svg>';
        $html .= '</div>';
    }

    // Input field
    $html .= '<input';
    $html .= ' type="' . htmlspecialchars((string)$options['type']) . '"';
    $html .= ' name="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' id="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' value="' . htmlspecialchars((string)$options['value']) . '"';
    $html .= ' class="' . htmlspecialchars($classes) . '"';

    if ($options['placeholder']) {
        $html .= ' placeholder="' . htmlspecialchars((string)$options['placeholder']) . '"';
    }

    if ($options['required']) {
        $html .= ' required';
    }

    if ($options['disabled']) {
        $html .= ' disabled';
    }

    if ($options['min'] !== '') {
        $html .= ' min="' . htmlspecialchars((string)$options['min']) . '"';
    }

    if ($options['max'] !== '') {
        $html .= ' max="' . htmlspecialchars((string)$options['max']) . '"';
    }

    if ($options['step'] !== '') {
        $html .= ' step="' . htmlspecialchars((string)$options['step']) . '"';
    }

    $html .= '>';

    // Close icon wrapper if present
    if ($options['icon']) {
        $html .= '</div>';
    }

    // Help text
    if ($options['help_text']) {
        $html .= '<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">' . htmlspecialchars((string)$options['help_text']) . '</p>';
    }

    // Error message
    if ($options['error']) {
        $html .= '<p class="mt-1 text-sm text-red-600 dark:text-red-400">' . htmlspecialchars((string)$options['error']) . '</p>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render a textarea field
 */
function renderTextarea(array $options): string
{
    $defaults = [
        'required' => false,
        'disabled' => false,
        'placeholder' => '',
        'value' => '',
        'rows' => 4,
        'class' => '',
        'help_text' => '',
        'error' => '',
    ];

    $options = array_merge($defaults, $options);

    $baseClasses = 'w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm';
    $errorClasses = $options['error'] ? 'border-red-300 dark:border-red-600' : 'border-gray-300 dark:border-gray-600';
    $disabledClasses = $options['disabled'] ? 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed' : '';

    $classes = trim($baseClasses . ' ' . $errorClasses . ' ' . $disabledClasses . ' ' . $options['class']);

    $html = '<div class="mb-4">';

    // Label
    if (!empty($options['label'])) {
        $html .= '<label for="' . htmlspecialchars((string)$options['name']) . '" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">';
        $html .= htmlspecialchars((string)$options['label']);
        if ($options['required']) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }

    // Textarea field
    $html .= '<textarea';
    $html .= ' name="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' id="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' rows="' . (int)$options['rows'] . '"';
    $html .= ' class="' . htmlspecialchars($classes) . '"';

    if ($options['placeholder']) {
        $html .= ' placeholder="' . htmlspecialchars((string)$options['placeholder']) . '"';
    }

    if ($options['required']) {
        $html .= ' required';
    }

    if ($options['disabled']) {
        $html .= ' disabled';
    }

    $html .= '>';
    $html .= htmlspecialchars((string)$options['value']);
    $html .= '</textarea>';

    // Help text
    if ($options['help_text']) {
        $html .= '<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">' . htmlspecialchars((string)$options['help_text']) . '</p>';
    }

    // Error message
    if ($options['error']) {
        $html .= '<p class="mt-1 text-sm text-red-600 dark:text-red-400">' . htmlspecialchars((string)$options['error']) . '</p>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render a select dropdown field
 */
function renderSelect(array $options): string
{
    $defaults = [
        'required' => false,
        'disabled' => false,
        'value' => '',
        'options' => [],
        'class' => '',
        'help_text' => '',
        'error' => '',
        'empty_option' => '',
        'icon' => '', // SVG path for icon
    ];

    $options = array_merge($defaults, $options);

    // Adjust padding based on whether icon is present
    $paddingClass = $options['icon'] ? 'pl-10 pr-3 py-2' : 'px-3 py-2';
    $baseClasses = 'w-full ' . $paddingClass . ' border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm';
    $errorClasses = $options['error'] ? 'border-red-300 dark:border-red-600' : 'border-gray-300 dark:border-gray-600';
    $disabledClasses = $options['disabled'] ? 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed' : '';

    $classes = trim($baseClasses . ' ' . $errorClasses . ' ' . $disabledClasses . ' ' . $options['class']);

    $html = '<div class="mb-4">';

    // Label
    if (!empty($options['label'])) {
        $html .= '<label for="' . htmlspecialchars((string)$options['name']) . '" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">';
        $html .= $options['label']; // Don't escape label as it may contain HTML like <span>
        if ($options['required']) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }

    // Select wrapper (for icon positioning)
    if ($options['icon']) {
        $html .= '<div class="relative rounded-md shadow-sm">';

        // Icon
        $html .= '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
        $html .= '<svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
        $html .= $options['icon'];
        $html .= '</svg>';
        $html .= '</div>';
    }

    // Select field
    $html .= '<select';
    $html .= ' name="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' id="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' class="' . htmlspecialchars($classes) . '"';

    if ($options['required']) {
        $html .= ' required';
    }

    if ($options['disabled']) {
        $html .= ' disabled';
    }

    $html .= '>';

    // Empty option
    if ($options['empty_option']) {
        $html .= '<option value="">' . htmlspecialchars((string)$options['empty_option']) . '</option>';
    }

    // Options
    foreach ($options['options'] as $value => $label) {
        $selected = (string)$value === (string)$options['value'] ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string)$value) . '"' . $selected . '>';
        $html .= htmlspecialchars((string)$label);
        $html .= '</option>';
    }

    $html .= '</select>';

    // Close icon wrapper if present
    if ($options['icon']) {
        $html .= '</div>';
    }

    // Help text
    if ($options['help_text']) {
        $html .= '<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">' . htmlspecialchars((string)$options['help_text']) . '</p>';
    }

    // Error message
    if ($options['error']) {
        $html .= '<p class="mt-1 text-sm text-red-600 dark:text-red-400">' . htmlspecialchars((string)$options['error']) . '</p>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render a checkbox field
 */
function renderCheckbox(array $options): string
{
    $defaults = [
        'checked' => false,
        'disabled' => false,
        'value' => '1',
        'class' => '',
        'help_text' => '',
        'error' => '',
    ];

    $options = array_merge($defaults, $options);

    $html = '<div class="mb-4">';
    $html .= '<div class="flex items-center">';

    // Checkbox input
    $html .= '<input';
    $html .= ' type="checkbox"';
    $html .= ' name="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' id="' . htmlspecialchars((string)$options['name']) . '"';
    $html .= ' value="' . htmlspecialchars((string)$options['value']) . '"';
    $html .= ' class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded ' . htmlspecialchars((string)$options['class']) . '"';

    if ($options['checked']) {
        $html .= ' checked';
    }

    if ($options['disabled']) {
        $html .= ' disabled';
    }

    $html .= '>';

    // Label
    if (!empty($options['label'])) {
        $html .= '<label for="' . htmlspecialchars((string)$options['name']) . '" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">';
        $html .= htmlspecialchars((string)$options['label']);
        $html .= '</label>';
    }

    $html .= '</div>';

    // Help text
    if ($options['help_text']) {
        $html .= '<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">' . htmlspecialchars((string)$options['help_text']) . '</p>';
    }

    // Error message
    if ($options['error']) {
        $html .= '<p class="mt-1 text-sm text-red-600 dark:text-red-400">' . htmlspecialchars((string)$options['error']) . '</p>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render form buttons
 */
function renderFormButtons(array $options = []): string
{
    $defaults = [
        'submit_text' => 'Save',
        'cancel_url' => '',
        'show_cancel' => true,
        'additional_buttons' => [],
    ];

    $options = array_merge($defaults, $options);

    $html = '<div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">';

    // Cancel button
    if ($options['show_cancel'] && $options['cancel_url']) {
        $html .= '<a href="' . htmlspecialchars((string)$options['cancel_url']) . '" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">';
        $html .= 'Cancel';
        $html .= '</a>';
    }

    // Additional buttons
    foreach ($options['additional_buttons'] as $button) {
        $html .= '<button type="' . htmlspecialchars((string)($button['type'] ?? 'button')) . '" class="' . htmlspecialchars((string)($button['class'] ?? 'bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md')) . '">';
        $html .= htmlspecialchars((string)$button['text']);
        $html .= '</button>';
    }

    // Submit button
    $html .= '<button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">';
    $html .= htmlspecialchars((string)$options['submit_text']);
    $html .= '</button>';

    $html .= '</div>';

    return $html;
}

/**
 * Render CSRF token field
 */
function renderCSRFToken(): string
{
    // BaseController::render() extract()s the csrfToken variable into view scope, but
    // a function body cannot see caller-scope variables - reading it here silently
    // produced value="" and every form using this helper was rejected by
    // CsrfMiddleware. The session is the token's real home (see
    // CsrfMiddleware::generateToken()).
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
}
