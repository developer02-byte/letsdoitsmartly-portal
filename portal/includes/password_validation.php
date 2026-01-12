<?php
/**
 * Password Validation
 * Validates passwords against Google Workspace requirements
 */

if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Validate password against Google Workspace requirements
 *
 * Requirements:
 * - Minimum 8 characters
 * - Maximum 100 characters
 * - Cannot contain username/email
 * - 3 of 4 complexity requirements (uppercase, lowercase, number, special)
 *
 * @param string $password The password to validate
 * @param string|null $email Optional email to check against
 * @return array ['valid' => bool, 'errors' => array, 'checks' => array]
 */
function validatePassword($password, $email = null) {
    $errors = [];

    // Individual requirement checks
    $checks = [
        'length' => strlen($password) >= 8,
        'max_length' => strlen($password) <= 100,
        'uppercase' => (bool) preg_match('/[A-Z]/', $password),
        'lowercase' => (bool) preg_match('/[a-z]/', $password),
        'number' => (bool) preg_match('/[0-9]/', $password),
        'special' => (bool) preg_match('/[!@#$%^&*(),.?":{}|<>\-_=+\[\]\\\\\/`~;\'£€¥]/', $password),
        'no_email' => true
    ];

    // Check if password contains username
    if ($email) {
        $username = explode('@', $email)[0];
        if (strlen($username) >= 3) {
            $checks['no_email'] = stripos($password, $username) === false;
        }
    }

    // Calculate complexity score (3 of 4 required)
    $complexity_count = array_sum([
        $checks['uppercase'],
        $checks['lowercase'],
        $checks['number'],
        $checks['special']
    ]);
    $checks['complexity'] = $complexity_count >= 3;

    // Build error messages
    if (!$checks['length']) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if (!$checks['max_length']) {
        $errors[] = 'Password must be 100 characters or less';
    }

    if (!$checks['complexity']) {
        $errors[] = 'Password must contain at least 3 of: uppercase letter, lowercase letter, number, special character';
    }

    if (!$checks['no_email']) {
        $errors[] = 'Password cannot contain your username';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'checks' => $checks
    ];
}

/**
 * Get password requirements as array for display
 *
 * @return array List of requirement descriptions
 */
function getPasswordRequirements() {
    return [
        'length' => 'At least 8 characters',
        'complexity' => '3 of: uppercase, lowercase, number, special character',
        'no_email' => 'Cannot contain your username'
    ];
}

/**
 * Format validation errors for display
 *
 * @param array $errors Array of error messages
 * @return string Formatted error message
 */
function formatPasswordErrors($errors) {
    if (count($errors) === 1) {
        return $errors[0];
    }
    return 'Password issues: ' . implode('; ', $errors);
}
