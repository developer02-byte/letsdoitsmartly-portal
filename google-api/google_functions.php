<?php
/**
 * Google Workspace API Helper Functions
 * Location: /home/ldsadmin/websiteconfig/google_functions.php
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Initialize Google Client
 */
function getGoogleClient() {
    global $config;
    
    try {
        $autoloadPath = $config['google']['api_path'] . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            throw new Exception("Google API library not found at: " . $autoloadPath);
        }
        require_once $autoloadPath;
        
        $credentialsPath = $config['google']['credentials_file'];
        if (!file_exists($credentialsPath)) {
            throw new Exception("Credentials file not found at: " . $credentialsPath);
        }
        
        $client = new Google_Client();
        $client->setApplicationName($config['site']['name']);
        $client->setAuthConfig($credentialsPath);
        $client->setScopes($config['google']['scopes']);
        $client->setSubject($config['google']['admin_email']);
        
        return $client;
        
    } catch (Exception $e) {
        throw new Exception("Failed to initialize Google Client: " . $e->getMessage());
    }
}

/**
 * Fetch all users from a specific domain
 */
function fetchGoogleWorkspaceUsers($domainName) {
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        $allUsers = [];
        $pageToken = null;
        
        do {
            $params = [
                'domain' => $domainName,
                'maxResults' => 500,
                'orderBy' => 'email',
                'projection' => 'full'
            ];
            
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }
            
            $results = $service->users->listUsers($params);
            $users = $results->getUsers();
            
            if ($users) {
                foreach ($users as $user) {
                    $allUsers[] = [
                        'email' => $user->getPrimaryEmail(),
                        'first_name' => $user->getName()->getGivenName() ?? '',
                        'last_name' => $user->getName()->getFamilyName() ?? '',
                        'full_name' => $user->getName()->getFullName() ?? '',
                        'suspended' => $user->getSuspended() ? 'suspended' : 'active',
                        'org_unit_path' => $user->getOrgUnitPath() ?? '/',
                        'creation_time' => $user->getCreationTime() ?? null,
                        'last_login_time' => $user->getLastLoginTime() ?? null,
                        'is_admin' => $user->getIsAdmin() ?? false,
                        'is_delegated_admin' => $user->getIsDelegatedAdmin() ?? false
                    ];
                }
            }
            
            $pageToken = $results->getNextPageToken();
            
        } while ($pageToken);
        
        return [
            'success' => true,
            'users' => $allUsers,
            'total' => count($allUsers)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'users' => [],
            'total' => 0
        ];
    }
}

/**
 * Get storage information for a user
 */
function getUserStorageInfo($userEmail) {
    try {
        $client = getGoogleClient();
        $reportsService = new Google_Service_Reports($client);
        
        $params = [
            'userKey' => $userEmail

        ];
        
        // Get user usage report
        $usageReports = $reportsService->userUsageReport->get('all', 'latest', $params);
        
        $storageUsed = 0;
        $storageLimit = 0;
        
        if ($usageReports->getUsageReports()) {
            foreach ($usageReports->getUsageReports() as $report) {
                $parameters = $report->getParameters();
                foreach ($parameters as $param) {
                    $name = $param->getName();
                    $value = $param->getIntValue();
                    
                    // Storage metrics
                    if ($name === 'accounts:total_quota_in_mb') {
                        $storageLimit = $value; // Already in MB
                    } elseif ($name === 'accounts:used_quota_in_mb') {
                        $storageUsed = $value; // Already in MB
                    }
                }
            }
        }
        
        return [
            'storage_used_mb' => $storageUsed,
            'storage_limit_mb' => $storageLimit > 0 ? $storageLimit : 30720 // Default 30GB if not found
        ];
        
    } catch (Exception $e) {
        // Return defaults if storage info unavailable
        return [
            'storage_used_mb' => 0,
            'storage_limit_mb' => 30720 // Default 30GB
        ];
    }
}

/**
 * Import users from Google Workspace to database
 */
function importGoogleUsersToDatabase($conn, $domainId, $domainName, $domainOwnerId) {
    global $config;
    
    $log_id = null;
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $error_details = []; // NEW: Track error details
    
    try {
        // Create import log entry
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $sql = "INSERT INTO import_logs (domain_owner_id, domain_id, import_type, domain_name, status, ip_address) 
                VALUES (?, ?, 'single_domain', ?, 'in_progress', ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $domainOwnerId, $domainId, $domainName, $ip);
        mysqli_stmt_execute($stmt);
        $log_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Fetch users from Google Workspace
        $result = fetchGoogleWorkspaceUsers($domainName);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        $users = $result['users'];
        $total_fetched = count($users);
        
        // Update log with fetched count
        mysqli_query($conn, "UPDATE import_logs SET total_fetched = $total_fetched WHERE id = $log_id");
        
        // Import each user
        foreach ($users as $userData) {
            try {
                // NEW: Validate user data
                if (empty($userData['email'])) {
                    $errors++;
                    $error_details[] = "User has no email address";
                    continue;
                }
                
                // Check if email already exists
                $check_sql = "SELECT id FROM email_accounts WHERE email_address = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $userData['email']);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    // Email exists, skip
                    $skipped++;
                    mysqli_stmt_close($check_stmt);
                    continue;
                }
                mysqli_stmt_close($check_stmt);
                
                // Use defaults for storage
                $storage_used = 0;
                $storage_limit = 30720; // 30GB default
                
                // NEW: Better status handling
                $status = 'active';
                if (isset($userData['suspended'])) {
                    $status = ($userData['suspended'] === true || $userData['suspended'] === 'true') ? 'suspended' : 'active';
                }
                
                // Insert new email account
                $insert_sql = "INSERT INTO email_accounts 
                               (domain_id, email_address, storage_limit, storage_used, status, created_at, last_login) 
                               VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                
                $last_login = null;
                $last_login = null;
                if (isset($userData['last_login_time']) && !empty($userData['last_login_time'])) {
                    $timestamp = strtotime($userData['last_login_time']);
                    // Only set if valid timestamp (after 1971-01-01)
                    if ($timestamp && $timestamp > 31536000) {
                        $last_login = date('Y-m-d H:i:s', $timestamp);
                    }
                }

                
                mysqli_stmt_bind_param($insert_stmt, "isiiss", 
                    $domainId, 
                    $userData['email'], 
                    $storage_limit, 
                    $storage_used, 
                    $status,
                    $last_login
                );
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    $imported++;
                } else {
                    $errors++;
                    $error_details[] = $userData['email'] . ": " . mysqli_stmt_error($insert_stmt);
                }
                mysqli_stmt_close($insert_stmt);
                
            } catch (Exception $e) {
                $errors++;
                $email = $userData['email'] ?? 'unknown';
                $error_details[] = $email . ": " . $e->getMessage();
            }
        }
        
        // Update license count
        $used_licenses_sql = "UPDATE licenses SET used_licenses = (
                                SELECT COUNT(*) FROM email_accounts ea 
                                JOIN domains d ON ea.domain_id = d.id 
                                WHERE d.domain_owner_id = ?
                              ) WHERE domain_owner_id = ?";
        $license_stmt = mysqli_prepare($conn, $used_licenses_sql);
        mysqli_stmt_bind_param($license_stmt, "ii", $domainOwnerId, $domainOwnerId);
        mysqli_stmt_execute($license_stmt);
        mysqli_stmt_close($license_stmt);
        
        // NEW: Save error details to log
        $error_message = !empty($error_details) ? implode(" | ", $error_details) : null;
        
        // Update import log as completed
        $update_sql = "UPDATE import_logs 
                       SET status = 'completed', 
                           total_imported = ?, 
                           total_skipped = ?, 
                           total_errors = ?,
                           error_message = ?,
                           completed_at = NOW() 
                       WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "iiisi", $imported, $skipped, $errors, $error_message, $log_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        // Update domain's last_google_sync timestamp
        $sync_sql = "UPDATE domains SET last_google_sync = NOW() WHERE id = ?";
        $sync_stmt = mysqli_prepare($conn, $sync_sql);
        mysqli_stmt_bind_param($sync_stmt, "i", $domainId);
        mysqli_stmt_execute($sync_stmt);
        mysqli_stmt_close($sync_stmt);

        return [
            'success' => true,
            'total_fetched' => $total_fetched,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $error_details // NEW: Return error details
        ];
        
    } catch (Exception $e) {
        // Update log as failed
        if ($log_id) {
            $error_msg = mysqli_real_escape_string($conn, $e->getMessage());
            mysqli_query($conn, "UPDATE import_logs 
                                SET status = 'failed', 
                                    error_message = '$error_msg',
                                    total_imported = $imported,
                                    total_skipped = $skipped,
                                    total_errors = $errors,
                                    completed_at = NOW() 
                                WHERE id = $log_id");
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'total_fetched' => 0,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
}


/**
 * Check if user has exceeded daily import limit
 */
function checkImportLimit($conn, $userId) {
    global $config;
    
    $daily_limit = $config['import']['daily_limit'];
    $today = date('Y-m-d');
    
    $sql = "SELECT COUNT(*) as count 
            FROM import_logs 
            WHERE domain_owner_id = ? 
            AND DATE(started_at) = ? 
            AND status IN ('completed', 'in_progress')";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $userId, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $count = $row['count'];
    $remaining = $daily_limit - $count;
    
    return [
        'allowed' => $count < $daily_limit,
        'count' => $count,
        'limit' => $daily_limit,
        'remaining' => max(0, $remaining)
    ];
}

/**
 * Get the Organizational Unit path for a domain
 * Fetches from the first user found in the domain
 */
function getDomainOrgUnit($domain) {
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        // Get first user from the domain
        $params = [
            'domain' => $domain,
            'maxResults' => 1,
            'orderBy' => 'email'
        ];
        
        $results = $service->users->listUsers($params);
        $users = $results->getUsers();
        
        if (count($users) > 0) {
            $firstUser = $users[0];
            $orgUnitPath = $firstUser->getOrgUnitPath();
            
            return [
                'success' => true,
                'orgUnitPath' => $orgUnitPath ?? '/'
            ];
        }
        
        // No users found, use root
        return [
            'success' => true,
            'orgUnitPath' => '/'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'orgUnitPath' => '/' // Default to root on error
        ];
    }
}
 


/**
 * Create a new user in Google Workspace
 */
function createGoogleWorkspaceUser($domain, $username, $firstName, $lastName, $password) {
    global $config;
    
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        $userEmail = $username . '@' . $domain;
        
        // Get the OU from existing users
        $ouResult = getDomainOrgUnit($domain);
        $orgUnitPath = $ouResult['orgUnitPath'];
        
        // Create user object
        $user = new Google_Service_Directory_User();
        $user->setPrimaryEmail($userEmail);
        
        // Set name
        $name = new Google_Service_Directory_UserName();
        $name->setGivenName($firstName);
        $name->setFamilyName($lastName);
        $user->setName($name);
        
        // Set password
        $user->setPassword($password);
        $user->setChangePasswordAtNextLogin(false);
        
        // Set Organizational Unit
        $user->setOrgUnitPath($orgUnitPath);
        
        // Create the user
        $createdUser = $service->users->insert($user);
        
        return [
            'success' => true,
            'user_id' => $createdUser->getId(),
            'email' => $userEmail,
            'orgUnitPath' => $orgUnitPath
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Delete a user from Google Workspace
 */
function deleteGoogleWorkspaceUser($email) {
    global $config;
    
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        // Delete the user
        $service->users->delete($email);
        
        return [
            'success' => true,
            'message' => 'User deleted successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Update user password in Google Workspace
 */
function updateGoogleUserPassword($email, $newPassword) {
    global $config;
    
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        // Create user update object
        $user = new Google_Service_Directory_User();
        $user->setPassword($newPassword);
        $user->setChangePasswordAtNextLogin(false);
        
        // Update the user
        $service->users->update($email, $user);
        
        return [
            'success' => true,
            'message' => 'Password updated successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Suspend/Resume a user in Google Workspace
 */
function toggleGoogleUserStatus($email, $suspend = true) {
    global $config;
    
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        $user = new Google_Service_Directory_User();
        $user->setSuspended($suspend);
        
        $service->users->update($email, $user);
        
        return [
            'success' => true,
            'message' => $suspend ? 'User suspended' : 'User activated'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
/**
 * Get all aliases for a user
 */
function getGoogleUserAliases($email) {
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        $user = $service->users->get($email);
        $aliases = $user->getAliases();
        
        return [
            'success' => true,
            'aliases' => $aliases ?? []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'aliases' => []
        ];
    }
}

/**
 * Add an alias to a user
 */
function addGoogleUserAlias($email, $aliasEmail) {
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        $alias = new Google_Service_Directory_Alias();
        $alias->setAlias($aliasEmail);
        
        $service->users_aliases->insert($email, $alias);
        
        return [
            'success' => true,
            'message' => 'Alias added successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Remove an alias from a user
 */
function removeGoogleUserAlias($email, $aliasEmail) {
    try {
        $client = getGoogleClient();
        $service = new Google_Service_Directory($client);
        
        $service->users_aliases->delete($email, $aliasEmail);
        
        return [
            'success' => true,
            'message' => 'Alias removed successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


?>
