<?php
/**
 * Error Messages and Codes
 * Maps technical errors to user-friendly messages
 */

if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

// Error code definitions
class ErrorCodes {
    // Google API Errors
    const GOOGLE_ENTITY_EXISTS = 'GOOGLE_ENTITY_EXISTS';
    const GOOGLE_NOT_FOUND = 'GOOGLE_NOT_FOUND';
    const GOOGLE_QUOTA_EXCEEDED = 'GOOGLE_QUOTA_EXCEEDED';
    const GOOGLE_RATE_LIMITED = 'GOOGLE_RATE_LIMITED';
    const GOOGLE_AUTH_FAILED = 'GOOGLE_AUTH_FAILED';
    const GOOGLE_PERMISSION_DENIED = 'GOOGLE_PERMISSION_DENIED';
    const GOOGLE_INVALID_INPUT = 'GOOGLE_INVALID_INPUT';
    const GOOGLE_NETWORK_ERROR = 'GOOGLE_NETWORK_ERROR';
    const GOOGLE_UNKNOWN = 'GOOGLE_UNKNOWN';

    // Database Errors
    const DB_INSERT_FAILED = 'DB_INSERT_FAILED';
    const DB_UPDATE_FAILED = 'DB_UPDATE_FAILED';
    const DB_DELETE_FAILED = 'DB_DELETE_FAILED';
    const DB_DUPLICATE = 'DB_DUPLICATE';
    const DB_CONNECTION_FAILED = 'DB_CONNECTION_FAILED';

    // Business Logic Errors
    const ACCESS_DENIED = 'ACCESS_DENIED';
    const INVALID_INPUT = 'INVALID_INPUT';
    const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    const OPERATION_IN_PROGRESS = 'OPERATION_IN_PROGRESS';
    const RESOURCE_LOCKED = 'RESOURCE_LOCKED';
}

/**
 * User-friendly error messages
 */
$ERROR_MESSAGES = [
    ErrorCodes::GOOGLE_ENTITY_EXISTS => [
        'title' => 'Already Exists',
        'message' => 'This email address or alias already exists in Google Workspace.',
        'action' => 'Try a different address or check if this account needs to be recovered.'
    ],
    ErrorCodes::GOOGLE_NOT_FOUND => [
        'title' => 'Not Found',
        'message' => 'The requested email account was not found in Google Workspace.',
        'action' => 'It may have been deleted. Try syncing to update the data.'
    ],
    ErrorCodes::GOOGLE_QUOTA_EXCEEDED => [
        'title' => 'Quota Exceeded',
        'message' => 'Google API quota has been temporarily exceeded.',
        'action' => 'Please wait a few minutes and try again.'
    ],
    ErrorCodes::GOOGLE_RATE_LIMITED => [
        'title' => 'Too Many Requests',
        'message' => 'Too many requests sent to Google. The system will automatically retry.',
        'action' => 'If this persists, wait a few minutes before trying again.'
    ],
    ErrorCodes::GOOGLE_AUTH_FAILED => [
        'title' => 'Authentication Error',
        'message' => 'Failed to authenticate with Google Workspace.',
        'action' => 'Please contact the system administrator.'
    ],
    ErrorCodes::GOOGLE_PERMISSION_DENIED => [
        'title' => 'Permission Denied',
        'message' => 'The system does not have permission to perform this operation.',
        'action' => 'Please contact the system administrator.'
    ],
    ErrorCodes::GOOGLE_INVALID_INPUT => [
        'title' => 'Invalid Input',
        'message' => 'The provided data was rejected by Google.',
        'action' => 'Check that all fields are properly formatted and try again.'
    ],
    ErrorCodes::GOOGLE_NETWORK_ERROR => [
        'title' => 'Connection Error',
        'message' => 'Could not connect to Google Workspace.',
        'action' => 'Please check your internet connection and try again.'
    ],
    ErrorCodes::GOOGLE_UNKNOWN => [
        'title' => 'Google API Error',
        'message' => 'An unexpected error occurred while communicating with Google.',
        'action' => 'Please try again. If the problem persists, contact support.'
    ],
    ErrorCodes::DB_INSERT_FAILED => [
        'title' => 'Save Failed',
        'message' => 'The operation completed in Google but failed to save locally.',
        'action' => 'The system is tracking this for automatic recovery. Contact admin if it persists.'
    ],
    ErrorCodes::DB_UPDATE_FAILED => [
        'title' => 'Update Failed',
        'message' => 'Failed to update the local database.',
        'action' => 'Please try again or contact support.'
    ],
    ErrorCodes::DB_DELETE_FAILED => [
        'title' => 'Delete Failed',
        'message' => 'The operation completed in Google but failed to update locally.',
        'action' => 'The system is tracking this for automatic recovery.'
    ],
    ErrorCodes::DB_DUPLICATE => [
        'title' => 'Duplicate Entry',
        'message' => 'This record already exists in the database.',
        'action' => 'Try refreshing the page or syncing with Google.'
    ],
    ErrorCodes::ACCESS_DENIED => [
        'title' => 'Access Denied',
        'message' => 'You do not have permission to perform this action.',
        'action' => 'Contact your administrator if you believe this is an error.'
    ],
    ErrorCodes::INVALID_INPUT => [
        'title' => 'Invalid Input',
        'message' => 'Please check your input and try again.',
        'action' => 'Ensure all required fields are filled correctly.'
    ],
    ErrorCodes::RESOURCE_NOT_FOUND => [
        'title' => 'Not Found',
        'message' => 'The requested resource could not be found.',
        'action' => 'It may have been deleted. Try refreshing the page.'
    ],
    ErrorCodes::OPERATION_IN_PROGRESS => [
        'title' => 'Operation In Progress',
        'message' => 'Another operation is currently in progress.',
        'action' => 'Please wait for it to complete and try again.'
    ],
    ErrorCodes::RESOURCE_LOCKED => [
        'title' => 'Resource Busy',
        'message' => 'This resource is currently being modified by another user.',
        'action' => 'Please wait a moment and try again.'
    ]
];

/**
 * Parse Google API error and return error code
 */
function parseGoogleError($message, $code = null) {
    // Try to extract JSON error from message
    $error = @json_decode($message, true);
    $reason = $error['error']['errors'][0]['reason'] ?? '';
    $errorMessage = $error['error']['message'] ?? $message;

    // Map to error codes based on message content and code
    if (strpos($errorMessage, 'Entity already exists') !== false ||
        strpos($message, 'Entity already exists') !== false) {
        return [ErrorCodes::GOOGLE_ENTITY_EXISTS, $errorMessage ?: $message];
    }

    if (strpos($errorMessage, 'Resource Not Found') !== false ||
        strpos($message, 'Resource Not Found') !== false) {
        return [ErrorCodes::GOOGLE_NOT_FOUND, $errorMessage ?: $message];
    }

    if ($code == 429 || $reason === 'rateLimitExceeded' || $reason === 'userRateLimitExceeded') {
        return [ErrorCodes::GOOGLE_RATE_LIMITED, $errorMessage ?: $message];
    }

    if ($reason === 'quotaExceeded') {
        return [ErrorCodes::GOOGLE_QUOTA_EXCEEDED, $errorMessage ?: $message];
    }

    if ($code == 401) {
        return [ErrorCodes::GOOGLE_AUTH_FAILED, $errorMessage ?: $message];
    }

    if ($code == 403) {
        if (strpos($errorMessage, 'Not Authorized') !== false) {
            return [ErrorCodes::GOOGLE_PERMISSION_DENIED, $errorMessage ?: $message];
        }
    }

    if ($code == 400) {
        return [ErrorCodes::GOOGLE_INVALID_INPUT, $errorMessage ?: $message];
    }

    if (strpos($message, 'Could not resolve') !== false ||
        strpos($message, 'Connection') !== false ||
        strpos($message, 'timed out') !== false) {
        return [ErrorCodes::GOOGLE_NETWORK_ERROR, $message];
    }

    return [ErrorCodes::GOOGLE_UNKNOWN, $errorMessage ?: $message];
}

/**
 * Get user-friendly error response
 */
function getFriendlyError($error_code, $technical_message = null) {
    global $ERROR_MESSAGES;

    $error_info = $ERROR_MESSAGES[$error_code] ?? [
        'title' => 'Error',
        'message' => 'An unexpected error occurred.',
        'action' => 'Please try again or contact support.'
    ];

    return [
        'code' => $error_code,
        'title' => $error_info['title'],
        'message' => $error_info['message'],
        'action' => $error_info['action'],
        'technical' => $technical_message
    ];
}

/**
 * Format error for display (HTML)
 */
function formatErrorHtml($error_code, $technical_message = null) {
    $error = getFriendlyError($error_code, $technical_message);
    return sprintf(
        '<strong>%s</strong>: %s<br><small class="text-muted">%s</small>',
        htmlspecialchars($error['title']),
        htmlspecialchars($error['message']),
        htmlspecialchars($error['action'])
    );
}

/**
 * Format error for URL parameter
 */
function formatErrorUrl($error_code, $technical_message = null) {
    $error = getFriendlyError($error_code, $technical_message);
    return urlencode($error['message'] . ' ' . $error['action']);
}

/**
 * Standard API error response format
 */
function apiErrorResponse($error_code, $technical_message = null, $http_code = 400) {
    if (!headers_sent()) {
        http_response_code($http_code);
    }
    $error = getFriendlyError($error_code, $technical_message);
    return [
        'success' => false,
        'error' => $error['message'],
        'error_details' => $error
    ];
}
