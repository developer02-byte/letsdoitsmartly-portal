<?php
/**
 * Google Workspace API Functions
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Get Google Client with service account authentication
 */
function getGoogleClient() {
    global $google_config;

    // Check if Google API library exists
    $autoload_path = $google_config['api_path'] . '/vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        throw new Exception('Google API library not found. Please install it on the server.');
    }

    require_once $autoload_path;

    // Check if credentials file exists
    if (!file_exists($google_config['credentials_file'])) {
        throw new Exception('Google credentials file not found.');
    }

    $client = new Google_Client();
    $client->setAuthConfig($google_config['credentials_file']);
    $client->setScopes($google_config['scopes']);
    $client->setSubject($google_config['admin_email']);

    return $client;
}

/**
 * Get Google Directory Service
 */
function getDirectoryService() {
    $client = getGoogleClient();
    return new Google_Service_Directory($client);
}

/**
 * Rate Limiting Configuration
 */
define('API_RETRY_MAX_ATTEMPTS', 5);
define('API_RETRY_BASE_DELAY', 1000); // 1 second in milliseconds
define('API_CALL_DELAY', 500); // 0.5 second delay between calls

/**
 * Execute API call with exponential backoff retry
 * Handles rate limit errors (429, 403 quota exceeded)
 */
function apiCallWithRetry($callback, $description = 'API call') {
    $attempts = 0;
    $lastError = null;

    while ($attempts < API_RETRY_MAX_ATTEMPTS) {
        try {
            // Add delay between API calls (except first call)
            if ($attempts > 0) {
                $delay = API_RETRY_BASE_DELAY * pow(2, $attempts - 1); // Exponential backoff
                usleep($delay * 1000); // Convert to microseconds
                error_log("Rate limit retry #{$attempts} for {$description}, waiting {$delay}ms");
            } else {
                // Standard delay between calls
                usleep(API_CALL_DELAY * 1000);
            }

            return $callback();

        } catch (Google_Service_Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $code = $e->getCode();
            $reason = $error['error']['errors'][0]['reason'] ?? '';

            // Check if it's a rate limit error
            $isRateLimit = (
                $code == 429 ||
                ($code == 403 && in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded']))
            );

            if ($isRateLimit && $attempts < API_RETRY_MAX_ATTEMPTS - 1) {
                $attempts++;
                $lastError = $e;
                error_log("Rate limit hit for {$description}: {$reason}. Attempt {$attempts}/" . API_RETRY_MAX_ATTEMPTS);
                continue;
            }

            throw $e; // Re-throw if not a rate limit error or max attempts reached
        }
    }

    throw $lastError ?? new Exception("Max retry attempts reached for {$description}");
}

/**
 * Fetch all users from a domain via Google Workspace
 */
function fetchGoogleWorkspaceUsers($domain) {
    try {
        $service = getDirectoryService();

        $users = [];
        $pageToken = null;

        do {
            $params = [
                'domain' => $domain,
                'maxResults' => 500,
                'orderBy' => 'email'
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $result = $service->users->listUsers($params);
            $pageUsers = $result->getUsers();

            if ($pageUsers) {
                foreach ($pageUsers as $user) {
                    $users[] = [
                        'google_user_id' => $user->getId(),
                        'email' => $user->getPrimaryEmail(),
                        'first_name' => $user->getName()->getGivenName(),
                        'last_name' => $user->getName()->getFamilyName(),
                        'suspended' => $user->getSuspended(),
                        'creation_time' => $user->getCreationTime(),
                        'last_login' => $user->getLastLoginTime(),
                        'aliases' => $user->getAliases() ?: []
                    ];
                }
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return ['success' => true, 'users' => $users];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();
        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create a new user in Google Workspace
 */
function createGoogleWorkspaceUser($email, $first_name, $last_name, $password) {
    try {
        $service = getDirectoryService();

        $user = new Google_Service_Directory_User([
            'primaryEmail' => $email,
            'name' => new Google_Service_Directory_UserName([
                'givenName' => $first_name,
                'familyName' => $last_name
            ]),
            'password' => $password,
            'changePasswordAtNextLogin' => false
        ]);

        $result = $service->users->insert($user);

        return [
            'success' => true,
            'google_user_id' => $result->getId(),
            'email' => $result->getPrimaryEmail()
        ];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();

        // Check for specific errors
        if (strpos($message, 'Entity already exists') !== false) {
            return ['success' => false, 'error' => 'This email address already exists in Google Workspace'];
        }

        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete a user from Google Workspace
 */
function deleteGoogleWorkspaceUser($email) {
    try {
        $service = getDirectoryService();
        $service->users->delete($email);

        return ['success' => true];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();

        // Check for not found error
        if (strpos($message, 'Resource Not Found') !== false) {
            return ['success' => true, 'warning' => 'User not found in Google (may have been already deleted)'];
        }

        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Toggle user suspension status in Google Workspace
 */
function toggleGoogleUserStatus($email, $suspend) {
    try {
        $service = getDirectoryService();

        $user = new Google_Service_Directory_User([
            'suspended' => $suspend
        ]);

        $service->users->update($email, $user);

        return ['success' => true, 'suspended' => $suspend];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();
        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update user password in Google Workspace
 */
function updateGoogleUserPassword($email, $new_password) {
    try {
        $service = getDirectoryService();

        $user = new Google_Service_Directory_User([
            'password' => $new_password,
            'changePasswordAtNextLogin' => false
        ]);

        $service->users->update($email, $user);

        return ['success' => true];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();
        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add an alias to a user in Google Workspace
 */
function addGoogleUserAlias($user_email, $alias_email) {
    try {
        $service = getDirectoryService();

        $alias = new Google_Service_Directory_Alias([
            'alias' => $alias_email
        ]);

        $service->users_aliases->insert($user_email, $alias);

        return ['success' => true];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();

        // Check for duplicate alias error
        if (strpos($message, 'Entity already exists') !== false) {
            return ['success' => false, 'error' => 'This alias already exists'];
        }

        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Remove an alias from a user in Google Workspace
 */
function removeGoogleUserAlias($user_email, $alias_email) {
    try {
        $service = getDirectoryService();
        $service->users_aliases->delete($user_email, $alias_email);

        return ['success' => true];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();

        // Check for not found error
        if (strpos($message, 'Resource Not Found') !== false) {
            return ['success' => true, 'warning' => 'Alias not found in Google (may have been already deleted)'];
        }

        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fetch all aliases for a user from Google Workspace
 */
function fetchGoogleUserAliases($user_email) {
    try {
        $service = getDirectoryService();
        $result = $service->users_aliases->listUsersAliases($user_email);

        $aliases = [];
        if ($result->getAliases()) {
            foreach ($result->getAliases() as $alias) {
                $aliases[] = $alias->getAlias();
            }
        }

        return ['success' => true, 'aliases' => $aliases];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();
        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check if domain exists in Google Workspace
 */
function checkGoogleDomain($domain) {
    try {
        $service = getDirectoryService();
        $result = $service->domains->get('my_customer', $domain);

        return [
            'success' => true,
            'domain' => $result->getDomainName(),
            'verified' => $result->getVerified(),
            'primary' => $result->getIsPrimary()
        ];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();

        if (strpos($message, 'Resource Not Found') !== false) {
            return ['success' => false, 'error' => 'Domain not found in Google Workspace'];
        }

        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fetch all domains from Google Workspace
 */
function fetchGoogleDomains() {
    try {
        $service = getDirectoryService();
        $result = $service->domains->listDomains('my_customer');

        $domains = [];
        if ($result->getDomains()) {
            foreach ($result->getDomains() as $domain) {
                $domains[] = [
                    'domain_name' => $domain->getDomainName(),
                    'verified' => $domain->getVerified(),
                    'primary' => $domain->getIsPrimary()
                ];
            }
        }

        return ['success' => true, 'domains' => $domains];

    } catch (Google_Service_Exception $e) {
        $error = json_decode($e->getMessage(), true);
        $message = $error['error']['message'] ?? $e->getMessage();
        return ['success' => false, 'error' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Sync users from Google Workspace to database
 */
function syncGoogleUsersToDatabase($domain_id, $domain_name, $conn) {
    // Fetch users from Google
    $google_result = fetchGoogleWorkspaceUsers($domain_name);

    if (!$google_result['success']) {
        return $google_result;
    }

    $google_users = $google_result['users'];
    $stats = [
        'total_fetched' => count($google_users),
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];

    foreach ($google_users as $guser) {
        // Check if user exists in database
        $stmt = $conn->prepare("SELECT id, status FROM email_accounts WHERE email_address = ?");
        $stmt->bind_param('s', $guser['email']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        $status = $guser['suspended'] ? 'suspended' : 'active';

        if ($existing) {
            // Update existing user
            $update = $conn->prepare("
                UPDATE email_accounts
                SET first_name = ?, last_name = ?, status = ?, google_user_id = ?
                WHERE id = ?
            ");
            $update->bind_param('ssssi', $guser['first_name'], $guser['last_name'], $status, $guser['google_user_id'], $existing['id']);
            if ($update->execute()) {
                $stats['updated']++;
            } else {
                $stats['errors']++;
            }
        } else {
            // Insert new user
            $insert = $conn->prepare("
                INSERT INTO email_accounts (domain_id, email_address, first_name, last_name, status, google_user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param('isssss', $domain_id, $guser['email'], $guser['first_name'], $guser['last_name'], $status, $guser['google_user_id']);
            if ($insert->execute()) {
                $stats['imported']++;

                // Sync aliases for this user
                $email_account_id = $conn->insert_id;
                foreach ($guser['aliases'] as $alias) {
                    $alias_insert = $conn->prepare("
                        INSERT IGNORE INTO email_aliases (email_account_id, alias_address)
                        VALUES (?, ?)
                    ");
                    $alias_insert->bind_param('is', $email_account_id, $alias);
                    $alias_insert->execute();
                }
            } else {
                $stats['errors']++;
            }
        }
    }

    // Update domain last sync time
    $conn->query("UPDATE domains SET last_google_sync = NOW() WHERE id = $domain_id");

    return ['success' => true, 'stats' => $stats];
}

/**
 * Sync ALL domains from Google Workspace
 * Creates new domains as unassigned (NULL owner)
 * Detects and marks domains removed from Google
 */
function syncAllDomainsFromGoogle($conn) {
    $result = fetchGoogleDomains();

    if (!$result['success']) {
        return $result;
    }

    $stats = [
        'total_google' => count($result['domains']),
        'new_domains' => 0,
        'existing_domains' => 0,
        'removed_domains' => 0,
        'restored_domains' => 0,
        'errors' => []
    ];

    // Build list of domain names from Google
    $google_domain_names = array_column($result['domains'], 'domain_name');

    foreach ($result['domains'] as $domain) {
        $domain_name = $domain['domain_name'];

        // Check if domain exists
        $stmt = $conn->prepare("SELECT id, google_status FROM domains WHERE domain_name = ?");
        $stmt->bind_param('s', $domain_name);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $stats['existing_domains']++;

            // Check if domain was previously marked as removed but now reappears
            if (isset($existing['google_status']) && $existing['google_status'] === 'removed') {
                // Domain is back in Google - restore it
                $restore = $conn->prepare("
                    UPDATE domains
                    SET google_status = 'active', removed_at = NULL
                    WHERE id = ?
                ");
                $restore->bind_param('i', $existing['id']);
                if ($restore->execute()) {
                    $stats['restored_domains']++;
                    error_log("DOMAIN RESTORED: {$domain_name} is back in Google Workspace");

                    // Note: Users will be re-synced by the normal sync process
                }
            }
        } else {
            // Insert new domain with NULL owner (unassigned)
            $insert = $conn->prepare("
                INSERT INTO domains (domain_name, domain_owner_id, status, google_status, created_at)
                VALUES (?, NULL, 'active', 'active', NOW())
            ");
            $insert->bind_param('s', $domain_name);

            if ($insert->execute()) {
                $stats['new_domains']++;
            } else {
                $stats['errors'][] = "Failed to insert domain: {$domain_name}";
            }
        }
    }

    // Detect domains removed from Google
    // Only check domains that are currently marked as active in Google
    $db_domains = $conn->query("
        SELECT id, domain_name
        FROM domains
        WHERE google_status = 'active' OR google_status IS NULL
    ");

    while ($db_domain = $db_domains->fetch_assoc()) {
        if (!in_array($db_domain['domain_name'], $google_domain_names)) {
            // Domain exists in DB but not in Google - mark as removed
            $mark_removed = $conn->prepare("
                UPDATE domains
                SET google_status = 'removed', removed_at = NOW()
                WHERE id = ?
            ");
            $mark_removed->bind_param('i', $db_domain['id']);

            if ($mark_removed->execute()) {
                $stats['removed_domains']++;
                error_log("DOMAIN REMOVED: {$db_domain['domain_name']} no longer in Google Workspace");

                // Cascade: Mark all email accounts as domain_removed
                $cascade = $conn->prepare("
                    UPDATE email_accounts
                    SET status = 'domain_removed'
                    WHERE domain_id = ? AND status != 'domain_removed'
                ");
                $cascade->bind_param('i', $db_domain['id']);
                $cascade->execute();
                $affected = $cascade->affected_rows;

                if ($affected > 0) {
                    error_log("DOMAIN REMOVED: Marked {$affected} email accounts as domain_removed");
                }
            } else {
                $stats['errors'][] = "Failed to mark domain as removed: {$db_domain['domain_name']}";
            }
        }
    }

    return ['success' => true, 'stats' => $stats];
}

/**
 * Full sync: All domains, users, and aliases
 * Returns progress data for streaming updates
 */
function fullGoogleSync($conn, $progressCallback = null) {
    $totalStats = [
        'domains' => ['total' => 0, 'new' => 0, 'existing' => 0],
        'users' => ['total' => 0, 'imported' => 0, 'updated' => 0, 'errors' => 0],
        'current_domain' => '',
        'errors' => []
    ];

    // Step 1: Sync all domains
    if ($progressCallback) {
        $progressCallback(['step' => 'domains', 'message' => 'Fetching domains from Google...']);
    }

    $domainResult = syncAllDomainsFromGoogle($conn);
    if (!$domainResult['success']) {
        return $domainResult;
    }

    $totalStats['domains']['total'] = $domainResult['stats']['total_google'];
    $totalStats['domains']['new'] = $domainResult['stats']['new_domains'];
    $totalStats['domains']['existing'] = $domainResult['stats']['existing_domains'];

    // Step 2: Get all domains from database
    $domains = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active' ORDER BY domain_name ASC");

    $domainCount = $domains->num_rows;
    $currentDomain = 0;

    // Step 3: Sync users for each domain
    while ($domain = $domains->fetch_assoc()) {
        $currentDomain++;
        $totalStats['current_domain'] = $domain['domain_name'];

        if ($progressCallback) {
            $progressCallback([
                'step' => 'users',
                'domain' => $domain['domain_name'],
                'progress' => round(($currentDomain / $domainCount) * 100),
                'current' => $currentDomain,
                'total' => $domainCount
            ]);
        }

        $userResult = syncGoogleUsersToDatabase($domain['id'], $domain['domain_name'], $conn);

        if ($userResult['success']) {
            $totalStats['users']['total'] += $userResult['stats']['total_fetched'];
            $totalStats['users']['imported'] += $userResult['stats']['imported'];
            $totalStats['users']['updated'] += $userResult['stats']['updated'];
            $totalStats['users']['errors'] += $userResult['stats']['errors'];
        } else {
            $totalStats['errors'][] = "Domain {$domain['domain_name']}: " . ($userResult['error'] ?? 'Unknown error');
        }
    }

    $totalStats['current_domain'] = '';

    return ['success' => true, 'stats' => $totalStats];
}

/**
 * Sync Lock Functions
 * Prevents concurrent sync operations
 */

/**
 * Acquire sync lock
 * @param mysqli $conn Database connection
 * @param int $timeout_minutes Lock timeout in minutes
 * @param string $locked_by Identifier for who acquired the lock
 * @return array ['success' => bool, 'error' => string|null]
 */
function acquireSyncLock($conn, $timeout_minutes = 10, $locked_by = 'manual') {
    // First, check if there's an expired lock and clear it
    $timeout_sql = "UPDATE sync_locks
                    SET locked_at = NULL, locked_by = NULL
                    WHERE id = 1
                    AND locked_at IS NOT NULL
                    AND locked_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $stmt = $conn->prepare($timeout_sql);
    $stmt->bind_param('i', $timeout_minutes);
    $stmt->execute();

    // Try to acquire lock (only if not already locked)
    $acquire_sql = "UPDATE sync_locks
                    SET locked_at = NOW(), locked_by = ?
                    WHERE id = 1
                    AND locked_at IS NULL";
    $stmt = $conn->prepare($acquire_sql);
    $stmt->bind_param('s', $locked_by);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return ['success' => true];
    }

    // Lock was not acquired - check who has it
    $check = $conn->query("SELECT locked_by, locked_at FROM sync_locks WHERE id = 1");
    $lock_info = $check->fetch_assoc();

    return [
        'success' => false,
        'error' => 'Sync already in progress',
        'locked_by' => $lock_info['locked_by'] ?? 'unknown',
        'locked_at' => $lock_info['locked_at'] ?? null
    ];
}

/**
 * Release sync lock
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function releaseSyncLock($conn) {
    $release_sql = "UPDATE sync_locks SET locked_at = NULL, locked_by = NULL WHERE id = 1";
    return $conn->query($release_sql);
}

/**
 * Check if sync is currently locked
 * @param mysqli $conn Database connection
 * @param int $timeout_minutes Lock timeout in minutes
 * @return array ['locked' => bool, 'locked_by' => string|null, 'locked_at' => string|null]
 */
function isSyncLocked($conn, $timeout_minutes = 10) {
    $sql = "SELECT locked_at, locked_by FROM sync_locks WHERE id = 1";
    $result = $conn->query($sql);
    $lock = $result->fetch_assoc();

    if (!$lock || $lock['locked_at'] === null) {
        return ['locked' => false, 'locked_by' => null, 'locked_at' => null];
    }

    // Check if lock has expired
    $locked_time = strtotime($lock['locked_at']);
    $timeout_seconds = $timeout_minutes * 60;

    if ((time() - $locked_time) > $timeout_seconds) {
        // Lock expired
        return ['locked' => false, 'locked_by' => null, 'locked_at' => null, 'expired' => true];
    }

    return [
        'locked' => true,
        'locked_by' => $lock['locked_by'],
        'locked_at' => $lock['locked_at']
    ];
}

/**
 * =============================================================================
 * DOMAIN STATUS HELPERS
 * =============================================================================
 */

/**
 * Check if a domain has been removed from Google Workspace
 *
 * @param mysqli $conn Database connection
 * @param int $domain_id Domain ID
 * @return array ['removed' => bool, 'removed_at' => string|null]
 */
function isDomainRemovedFromGoogle($conn, $domain_id) {
    $stmt = $conn->prepare("SELECT google_status, removed_at FROM domains WHERE id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $domain = $stmt->get_result()->fetch_assoc();

    if (!$domain) {
        return ['removed' => false, 'removed_at' => null, 'error' => 'Domain not found'];
    }

    return [
        'removed' => ($domain['google_status'] === 'removed'),
        'removed_at' => $domain['removed_at']
    ];
}

/**
 * Get count of domains removed from Google
 *
 * @param mysqli $conn Database connection
 * @return int Count of removed domains
 */
function getRemovedDomainsCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM domains WHERE google_status = 'removed'");
    $row = $result->fetch_assoc();
    return (int) $row['count'];
}

/**
 * =============================================================================
 * SELF-HEALING SYNC FUNCTIONS
 * Detect and fix data inconsistencies between Google and local database
 * =============================================================================
 */

/**
 * Detect data inconsistencies for a domain
 * Finds: orphan DB records, stale aliases, missing aliases
 *
 * @param mysqli $conn Database connection
 * @param int $domain_id Domain ID
 * @param string $domain_name Domain name
 * @return array Inconsistency details
 */
function detectDataInconsistencies($conn, $domain_id, $domain_name) {
    $inconsistencies = [
        'orphan_emails' => [],      // In DB but not in Google
        'stale_aliases' => [],      // In DB but not in Google
        'missing_aliases' => [],    // In Google but not in DB
        'status_mismatches' => []   // Active/suspended mismatch
    ];

    // Fetch current state from Google
    $google_result = fetchGoogleWorkspaceUsers($domain_name);
    if (!$google_result['success']) {
        return [
            'success' => false,
            'error' => 'Failed to fetch Google users: ' . $google_result['error']
        ];
    }

    $google_users = $google_result['users'];
    $google_emails = array_column($google_users, 'email');
    $google_user_map = [];
    foreach ($google_users as $guser) {
        $google_user_map[$guser['email']] = $guser;
    }

    // Get all DB emails for this domain
    $stmt = $conn->prepare("SELECT id, email_address, status FROM email_accounts WHERE domain_id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $db_emails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Check for orphan emails (in DB but not Google)
    foreach ($db_emails as $db_email) {
        if (!in_array($db_email['email_address'], $google_emails)) {
            $inconsistencies['orphan_emails'][] = [
                'id' => $db_email['id'],
                'email' => $db_email['email_address'],
                'db_status' => $db_email['status']
            ];
        } else {
            // Check status mismatch
            $guser = $google_user_map[$db_email['email_address']];
            $google_status = $guser['suspended'] ? 'suspended' : 'active';
            if ($db_email['status'] !== $google_status) {
                $inconsistencies['status_mismatches'][] = [
                    'id' => $db_email['id'],
                    'email' => $db_email['email_address'],
                    'db_status' => $db_email['status'],
                    'google_status' => $google_status
                ];
            }
        }
    }

    // Get all DB aliases for this domain
    $alias_stmt = $conn->prepare("
        SELECT ea.id, ea.alias_address, ea.email_account_id, e.email_address as parent_email
        FROM email_aliases ea
        JOIN email_accounts e ON ea.email_account_id = e.id
        WHERE e.domain_id = ?
    ");
    $alias_stmt->bind_param('i', $domain_id);
    $alias_stmt->execute();
    $db_aliases = $alias_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build Google alias map
    $google_aliases = [];
    foreach ($google_users as $guser) {
        foreach ($guser['aliases'] as $alias) {
            $google_aliases[$alias] = $guser['email'];
        }
    }

    // Check for stale aliases (in DB but not Google)
    foreach ($db_aliases as $db_alias) {
        if (!isset($google_aliases[$db_alias['alias_address']])) {
            $inconsistencies['stale_aliases'][] = [
                'id' => $db_alias['id'],
                'alias' => $db_alias['alias_address'],
                'parent_email' => $db_alias['parent_email']
            ];
        }
    }

    // Check for missing aliases (in Google but not DB)
    $db_alias_addresses = array_column($db_aliases, 'alias_address');
    foreach ($google_aliases as $alias => $parent_email) {
        if (!in_array($alias, $db_alias_addresses)) {
            $inconsistencies['missing_aliases'][] = [
                'alias' => $alias,
                'parent_email' => $parent_email
            ];
        }
    }

    return [
        'success' => true,
        'domain_id' => $domain_id,
        'domain_name' => $domain_name,
        'inconsistencies' => $inconsistencies,
        'summary' => [
            'orphan_emails' => count($inconsistencies['orphan_emails']),
            'stale_aliases' => count($inconsistencies['stale_aliases']),
            'missing_aliases' => count($inconsistencies['missing_aliases']),
            'status_mismatches' => count($inconsistencies['status_mismatches']),
            'total_issues' => count($inconsistencies['orphan_emails']) +
                             count($inconsistencies['stale_aliases']) +
                             count($inconsistencies['missing_aliases']) +
                             count($inconsistencies['status_mismatches'])
        ]
    ];
}

/**
 * Self-healing sync - Automatically fix detected inconsistencies
 *
 * @param mysqli $conn Database connection
 * @param int $domain_id Domain ID
 * @param string $domain_name Domain name
 * @param array $options Options for what to fix
 * @return array Results of healing operations
 */
function selfHealingSync($conn, $domain_id, $domain_name, $options = []) {
    $defaults = [
        'fix_orphans' => true,
        'fix_stale_aliases' => true,
        'fix_missing_aliases' => true,
        'fix_status_mismatches' => true,
        'dry_run' => false
    ];
    $options = array_merge($defaults, $options);

    // First detect all inconsistencies
    $detection = detectDataInconsistencies($conn, $domain_id, $domain_name);
    if (!$detection['success']) {
        return $detection;
    }

    $results = [
        'orphans_removed' => 0,
        'stale_aliases_removed' => 0,
        'missing_aliases_added' => 0,
        'status_mismatches_fixed' => 0,
        'errors' => []
    ];

    $inconsistencies = $detection['inconsistencies'];

    // Fix orphan emails (remove from DB)
    if ($options['fix_orphans'] && !empty($inconsistencies['orphan_emails'])) {
        foreach ($inconsistencies['orphan_emails'] as $orphan) {
            if ($options['dry_run']) {
                $results['orphans_removed']++;
                continue;
            }

            // Delete aliases first
            $conn->query("DELETE FROM email_aliases WHERE email_account_id = " . intval($orphan['id']));

            // Delete the orphan email
            $stmt = $conn->prepare("DELETE FROM email_accounts WHERE id = ?");
            $stmt->bind_param('i', $orphan['id']);
            if ($stmt->execute()) {
                $results['orphans_removed']++;
                error_log("SELF-HEALING: Removed orphan email {$orphan['email']} from DB");
            } else {
                $results['errors'][] = "Failed to remove orphan: {$orphan['email']}";
            }
        }
    }

    // Fix stale aliases (remove from DB)
    if ($options['fix_stale_aliases'] && !empty($inconsistencies['stale_aliases'])) {
        foreach ($inconsistencies['stale_aliases'] as $stale) {
            if ($options['dry_run']) {
                $results['stale_aliases_removed']++;
                continue;
            }

            $stmt = $conn->prepare("DELETE FROM email_aliases WHERE id = ?");
            $stmt->bind_param('i', $stale['id']);
            if ($stmt->execute()) {
                $results['stale_aliases_removed']++;
                error_log("SELF-HEALING: Removed stale alias {$stale['alias']} from DB");
            } else {
                $results['errors'][] = "Failed to remove stale alias: {$stale['alias']}";
            }
        }
    }

    // Fix missing aliases (add to DB)
    if ($options['fix_missing_aliases'] && !empty($inconsistencies['missing_aliases'])) {
        foreach ($inconsistencies['missing_aliases'] as $missing) {
            if ($options['dry_run']) {
                $results['missing_aliases_added']++;
                continue;
            }

            // Get email account ID
            $stmt = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
            $stmt->bind_param('s', $missing['parent_email']);
            $stmt->execute();
            $email = $stmt->get_result()->fetch_assoc();

            if ($email) {
                $insert = $conn->prepare("INSERT IGNORE INTO email_aliases (email_account_id, alias_address) VALUES (?, ?)");
                $insert->bind_param('is', $email['id'], $missing['alias']);
                if ($insert->execute() && $insert->affected_rows > 0) {
                    $results['missing_aliases_added']++;
                    error_log("SELF-HEALING: Added missing alias {$missing['alias']} to DB");
                }
            } else {
                $results['errors'][] = "Parent email not found for alias: {$missing['alias']}";
            }
        }
    }

    // Fix status mismatches (update DB to match Google)
    if ($options['fix_status_mismatches'] && !empty($inconsistencies['status_mismatches'])) {
        foreach ($inconsistencies['status_mismatches'] as $mismatch) {
            if ($options['dry_run']) {
                $results['status_mismatches_fixed']++;
                continue;
            }

            $stmt = $conn->prepare("UPDATE email_accounts SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $mismatch['google_status'], $mismatch['id']);
            if ($stmt->execute()) {
                $results['status_mismatches_fixed']++;
                error_log("SELF-HEALING: Fixed status mismatch for {$mismatch['email']} ({$mismatch['db_status']} -> {$mismatch['google_status']})");
            } else {
                $results['errors'][] = "Failed to fix status for: {$mismatch['email']}";
            }
        }
    }

    return [
        'success' => true,
        'dry_run' => $options['dry_run'],
        'detection' => $detection['summary'],
        'results' => $results
    ];
}

/**
 * Enhanced sync that updates aliases for ALL users (not just new ones)
 * This is the improved version of syncGoogleUsersToDatabase
 *
 * @param int $domain_id Domain ID
 * @param string $domain_name Domain name
 * @param mysqli $conn Database connection
 * @return array Sync results
 */
function syncGoogleUsersToDatabaseEnhanced($domain_id, $domain_name, $conn) {
    // Fetch users from Google
    $google_result = fetchGoogleWorkspaceUsers($domain_name);

    if (!$google_result['success']) {
        return $google_result;
    }

    $google_users = $google_result['users'];
    $stats = [
        'total_fetched' => count($google_users),
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'aliases_added' => 0,
        'aliases_removed' => 0
    ];

    foreach ($google_users as $guser) {
        // Check if user exists in database
        $stmt = $conn->prepare("SELECT id, status FROM email_accounts WHERE email_address = ?");
        $stmt->bind_param('s', $guser['email']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        $status = $guser['suspended'] ? 'suspended' : 'active';

        if ($existing) {
            $email_account_id = $existing['id'];

            // Update existing user
            $update = $conn->prepare("
                UPDATE email_accounts
                SET first_name = ?, last_name = ?, status = ?, google_user_id = ?
                WHERE id = ?
            ");
            $update->bind_param('ssssi', $guser['first_name'], $guser['last_name'], $status, $guser['google_user_id'], $existing['id']);
            if ($update->execute()) {
                $stats['updated']++;
            } else {
                $stats['errors']++;
            }

            // ENHANCED: Sync aliases for existing users too
            $alias_stats = syncAliasesForUser($conn, $email_account_id, $guser['aliases']);
            $stats['aliases_added'] += $alias_stats['added'];
            $stats['aliases_removed'] += $alias_stats['removed'];

        } else {
            // Insert new user
            $insert = $conn->prepare("
                INSERT INTO email_accounts (domain_id, email_address, first_name, last_name, status, google_user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param('isssss', $domain_id, $guser['email'], $guser['first_name'], $guser['last_name'], $status, $guser['google_user_id']);
            if ($insert->execute()) {
                $stats['imported']++;

                // Sync aliases for new user
                $email_account_id = $conn->insert_id;
                $alias_stats = syncAliasesForUser($conn, $email_account_id, $guser['aliases']);
                $stats['aliases_added'] += $alias_stats['added'];
            } else {
                $stats['errors']++;
            }
        }
    }

    // Update domain last sync time
    $conn->query("UPDATE domains SET last_google_sync = NOW() WHERE id = $domain_id");

    return ['success' => true, 'stats' => $stats];
}

/**
 * Sync aliases for a specific user
 * Adds missing aliases and removes stale ones
 *
 * @param mysqli $conn Database connection
 * @param int $email_account_id Email account ID
 * @param array $google_aliases Aliases from Google
 * @return array Stats for aliases synced
 */
function syncAliasesForUser($conn, $email_account_id, $google_aliases) {
    $stats = ['added' => 0, 'removed' => 0];

    // Get current DB aliases for this user
    $stmt = $conn->prepare("SELECT id, alias_address FROM email_aliases WHERE email_account_id = ?");
    $stmt->bind_param('i', $email_account_id);
    $stmt->execute();
    $db_aliases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $db_alias_addresses = array_column($db_aliases, 'alias_address');
    $db_alias_map = [];
    foreach ($db_aliases as $a) {
        $db_alias_map[$a['alias_address']] = $a['id'];
    }

    // Add missing aliases (in Google but not DB)
    foreach ($google_aliases as $alias) {
        if (!in_array($alias, $db_alias_addresses)) {
            $insert = $conn->prepare("INSERT IGNORE INTO email_aliases (email_account_id, alias_address) VALUES (?, ?)");
            $insert->bind_param('is', $email_account_id, $alias);
            if ($insert->execute() && $insert->affected_rows > 0) {
                $stats['added']++;
            }
        }
    }

    // Remove stale aliases (in DB but not Google)
    foreach ($db_alias_addresses as $db_alias) {
        if (!in_array($db_alias, $google_aliases)) {
            $delete = $conn->prepare("DELETE FROM email_aliases WHERE id = ?");
            $delete->bind_param('i', $db_alias_map[$db_alias]);
            if ($delete->execute() && $delete->affected_rows > 0) {
                $stats['removed']++;
            }
        }
    }

    return $stats;
}

/**
 * Run full self-healing sync for all domains
 * Called by cron job
 *
 * @param mysqli $conn Database connection
 * @param callable|null $progressCallback Progress callback
 * @return array Full sync results
 */
function fullSelfHealingSync($conn, $progressCallback = null) {
    $totalStats = [
        'domains' => ['total' => 0, 'new' => 0, 'existing' => 0],
        'users' => ['total' => 0, 'imported' => 0, 'updated' => 0, 'errors' => 0],
        'aliases' => ['added' => 0, 'removed' => 0],
        'healing' => [
            'orphans_removed' => 0,
            'stale_aliases_removed' => 0,
            'status_mismatches_fixed' => 0
        ],
        'current_domain' => '',
        'errors' => []
    ];

    // Step 1: Sync all domains
    if ($progressCallback) {
        $progressCallback(['step' => 'domains', 'message' => 'Fetching domains from Google...']);
    }

    $domainResult = syncAllDomainsFromGoogle($conn);
    if (!$domainResult['success']) {
        return $domainResult;
    }

    $totalStats['domains']['total'] = $domainResult['stats']['total_google'];
    $totalStats['domains']['new'] = $domainResult['stats']['new_domains'];
    $totalStats['domains']['existing'] = $domainResult['stats']['existing_domains'];

    // Step 2: Get all domains from database
    $domains = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active' ORDER BY domain_name ASC");

    $domainCount = $domains->num_rows;
    $currentDomain = 0;

    // Step 3: Enhanced sync for each domain
    while ($domain = $domains->fetch_assoc()) {
        $currentDomain++;
        $totalStats['current_domain'] = $domain['domain_name'];

        if ($progressCallback) {
            $progressCallback([
                'step' => 'users',
                'domain' => $domain['domain_name'],
                'progress' => round(($currentDomain / $domainCount) * 100),
                'current' => $currentDomain,
                'total' => $domainCount
            ]);
        }

        // Use enhanced sync that handles aliases for all users
        $userResult = syncGoogleUsersToDatabaseEnhanced($domain['id'], $domain['domain_name'], $conn);

        if ($userResult['success']) {
            $totalStats['users']['total'] += $userResult['stats']['total_fetched'];
            $totalStats['users']['imported'] += $userResult['stats']['imported'];
            $totalStats['users']['updated'] += $userResult['stats']['updated'];
            $totalStats['users']['errors'] += $userResult['stats']['errors'];
            $totalStats['aliases']['added'] += $userResult['stats']['aliases_added'];
            $totalStats['aliases']['removed'] += $userResult['stats']['aliases_removed'];
        } else {
            $totalStats['errors'][] = "Domain {$domain['domain_name']}: " . ($userResult['error'] ?? 'Unknown error');
        }

        // Run self-healing for this domain
        $healResult = selfHealingSync($conn, $domain['id'], $domain['domain_name'], [
            'fix_orphans' => true,
            'fix_stale_aliases' => false, // Already handled by enhanced sync
            'fix_missing_aliases' => false, // Already handled by enhanced sync
            'fix_status_mismatches' => true
        ]);

        if ($healResult['success']) {
            $totalStats['healing']['orphans_removed'] += $healResult['results']['orphans_removed'];
            $totalStats['healing']['status_mismatches_fixed'] += $healResult['results']['status_mismatches_fixed'];
        }
    }

    $totalStats['current_domain'] = '';

    return ['success' => true, 'stats' => $totalStats];
}
