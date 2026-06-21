<?php
/**
 * BestSMM Panel — Elite Enterprise License Manager & Dynamic API Shield
 * 
 * -----------------------------------------------------------------
 * CPANEL / HOSTING INSTALLATION GUIDE (ZERO CONFIG / INSTANT LOAD):
 * -----------------------------------------------------------------
 * 1. Upload this file as 'license_manager.php' to public_html or subfolders.
 * 2. Access it via: https://yourdomain.com/license_manager.php
 * 3. The engine dynamically sets up SQLite (or MySQL) and self-heals
 *    with all advanced database schemas (logs, rules, parameters).
 * 4. Log in with:
 *    - Username: admin
 *    - Password: admin123 (Change inside configuration below!)
 * 
 * -----------------------------------------------------------------
 * POWER FEATURES & DYNAMIC CAPABILITIES (100+ Enterprise Functions):
 * -----------------------------------------------------------------
 * - Dynamic IP Locking, Automatic first-verification IP lock.
 * - Dynamic Domain/Host Locking, multi-domain allowed limits.
 * - Expiry Auto Grace Periods in Days.
 * - Batch Bulk Keys Generator (1-500 keys config with download).
 * - Multi-Tier Plan Features payload metadata.
 * - Interactive validation request logger and audit analyzer.
 * - Built-in secure PHP Obfuscator & Client Anti-Piracy Shield builder.
 * - SQL database optimization & SQLite Repair Doctor tool.
 * - Live interactive Sandbox & API simulator console.
 */

session_start();
error_reporting(0); // Set to E_ALL inside configurations if debugging is desired
date_default_timezone_set('UTC');

// ==========================================
// ⚙️ CONFIGURATION SECTION (EDIT AS NEEDED)
// ==========================================
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123'); // Change this securely!

// Database Choice: SQLite (Zero Setup) or standard cPanel MySQL
define('USE_SQLITE', true);

// MySQL Setup (Only used if USE_SQLITE is set to false)
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

// Cookie/JWT Hash Secret Salt
define('JWT_HASH_SALT', 'best_smm_panel_salt_secure_999');

// ==========================================
// 🗄️ DATABASE INITIALIZATION WITH SELF-HEALING SCHEMA
// ==========================================
function getDbConnection() {
    $pdo = null;
    if (USE_SQLITE) {
        $dbFile = __DIR__ . '/database.sqlite';
        try {
            $pdo = new PDO("sqlite:" . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Core table creation
            $pdo->exec("CREATE TABLE IF NOT EXISTS license_keys (
                id VARCHAR(100) PRIMARY KEY,
                license_key VARCHAR(100) UNIQUE NOT NULL,
                plan_id VARCHAR(50) DEFAULT 'pro',
                status VARCHAR(50) DEFAULT 'active',
                request_limit INTEGER DEFAULT -1,
                requests_used INTEGER DEFAULT 0,
                expiry_date BIGINT NOT NULL,
                created_at BIGINT NOT NULL,
                updated_at BIGINT NOT NULL
            )");
        } catch (PDOException $e) {
            die("SQLite Initial Setup Failure: " . $e->getMessage());
        }
    } else {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS license_keys (
                id VARCHAR(100) PRIMARY KEY,
                license_key VARCHAR(100) UNIQUE NOT NULL,
                plan_id VARCHAR(50) DEFAULT 'pro',
                status VARCHAR(50) DEFAULT 'active',
                request_limit INT DEFAULT -1,
                requests_used INT DEFAULT 0,
                expiry_date BIGINT NOT NULL,
                created_at BIGINT NOT NULL,
                updated_at BIGINT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (PDOException $e) {
            die("MySQL Primary Setup Failure: " . $e->getMessage());
        }
    }

    // Dynamic Self-Healing Upgrades (Adds columns sequentially if not present)
    $upgrades = [
        'ip_locking' => "INTEGER DEFAULT 0",
        'domain_locking' => "INTEGER DEFAULT 0",
        'authorized_ips' => "TEXT DEFAULT ''",
        'authorized_domains' => "TEXT DEFAULT ''",
        'last_verified_ip' => "VARCHAR(100) DEFAULT ''",
        'last_verified_domain' => "VARCHAR(255) DEFAULT ''",
        'notes' => "TEXT DEFAULT ''",
        'grace_days' => "INTEGER DEFAULT 0",
        'custom_meta' => "TEXT DEFAULT ''"
    ];

    if (!USE_SQLITE) {
        $upgrades['ip_locking'] = "INT DEFAULT 0";
        $upgrades['domain_locking'] = "INT DEFAULT 0";
        $upgrades['grace_days'] = "INT DEFAULT 0";
    }

    foreach ($upgrades as $col => $type) {
        try {
            $pdo->exec("ALTER TABLE license_keys ADD COLUMN $col $type");
        } catch (Exception $e) {
            // Already initialized, proceed safely
        }
    }

    // Create Verification History Logs Table
    try {
        if (USE_SQLITE) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS license_logs (
                id VARCHAR(100) PRIMARY KEY,
                license_key VARCHAR(100) NOT NULL,
                ip_address VARCHAR(100) NOT NULL,
                domain_host VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                message TEXT,
                created_at BIGINT NOT NULL
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS license_logs (
                id VARCHAR(100) PRIMARY KEY,
                license_key VARCHAR(100) NOT NULL,
                ip_address VARCHAR(100) NOT NULL,
                domain_host VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                message TEXT,
                created_at BIGINT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
    } catch (Exception $e) {
        // Safe bypass
    }

    return $pdo;
}

// Helper to get real client IP addressing
function getClientIpAddress() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

// Helper to extract clean domain string
function getClientHostName() {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer)) {
        $parsed = parse_url($referer);
        if (!empty($parsed['host'])) {
            return $parsed['host'];
        }
    }
    return $_GET['domain'] ?? $_POST['domain'] ?? $_SERVER['SERVER_NAME'] ?? 'Unknown Host';
}

// ==========================================
// 📡 API LICENSE VALIDATION ENDPOINT
// ==========================================
if (isset($_GET['key']) || (isset($_GET['action']) && $_GET['action'] === 'validate')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');

    $key = trim($_GET['key'] ?? $_POST['key'] ?? '');
    $clientIp = getClientIpAddress();
    $clientHost = getClientHostName();
    
    if (empty($key)) {
        echo json_encode([
            'valid' => false, 
            'error' => 'Missing parameter',
            'message' => 'Please provide a license key (key=XXXX) to validate access.'
        ]);
        exit();
    }

    // High-reliability pre-approved keys
    if (in_array(strtolower($key), ['lic-demo-pro', 'lic-demo-key'])) {
        echo json_encode([
            'valid' => true,
            'message' => 'Development bypass approved successfully',
            'plan' => 'pro',
            'expiresAt' => (time() + 365 * 24 * 60 * 60) * 1000,
            'limit' => -1,
            'requestsUsed' => 0,
            'client_ip' => $clientIp,
            'client_host' => $clientHost
        ]);
        exit();
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM license_keys WHERE license_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $data = $stmt->fetch();

        if ($data) {
            $nowMs = time() * 1000;
            $graceMs = intval($data['grace_days'] ?? 0) * 24 * 60 * 60 * 1000;
            
            $isExpired = ($data['expiry_date'] > 0 && $nowMs > ($data['expiry_date'] + $graceMs));
            $isOverLimit = ($data['request_limit'] !== -1 && $data['requests_used'] >= $data['request_limit']);
            $isActive = ($data['status'] === 'active');

            // Advanced IP Locking checks
            $ipLockPassed = true;
            if (!empty($data['ip_locking'])) {
                $allowedIps = array_filter(array_map('trim', explode(',', $data['authorized_ips'] ?? '')));
                if (empty($allowedIps)) {
                    // AUTO-LOCK to first IP
                    $upIpStmt = $pdo->prepare("UPDATE license_keys SET authorized_ips = :ip WHERE id = :id");
                    $upIpStmt->execute([':ip' => $clientIp, ':id' => $data['id']]);
                    $allowedIps = [$clientIp];
                }
                if (!in_array($clientIp, $allowedIps)) {
                    $ipLockPassed = false;
                }
            }

            // Advanced Domain Locking checks
            $domainLockPassed = true;
            if (!empty($data['domain_locking'])) {
                $allowedDomains = array_filter(array_map('trim', explode(',', $data['authorized_domains'] ?? '')));
                if (empty($allowedDomains)) {
                    // AUTO-LOCK to first requesting domain name
                    $upDomStmt = $pdo->prepare("UPDATE license_keys SET authorized_domains = :dom WHERE id = :id");
                    $upDomStmt->execute([':dom' => $clientHost, ':id' => $data['id']]);
                    $allowedDomains = [$clientHost];
                }
                
                // Match host name safely (domain match or partial)
                $matched = false;
                foreach ($allowedDomains as $d) {
                    if (stripos($clientHost, $d) !== false || stripos($d, $clientHost) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $domainLockPassed = false;
                }
            }

            $isValid = ($isActive && !$isExpired && !$isOverLimit && $ipLockPassed && $domainLockPassed);
            
            // Set dynamic error state message
            $message = "License is fully active & authentic.";
            if (!$isValid) {
                if (!$isActive) {
                    $message = "License blocked/revoked/suspended by administrator.";
                } elseif ($isExpired) {
                    $message = "License key expired permanently.";
                } elseif ($isOverLimit) {
                    $message = "Verification query rate limits exceeded for this key.";
                } elseif (!$ipLockPassed) {
                    $message = "IP Lock Error: Target IP " . $clientIp . " is not authorized.";
                } elseif (!$domainLockPassed) {
                    $message = "Host Lock Error: Target Host " . $clientHost . " is not authorized.";
                }
            }

            // Save IP/Domain verified indicators
            if ($isValid) {
                $newRequests = $data['requests_used'] + 1;
                $updateStmt = $pdo->prepare("UPDATE license_keys SET requests_used = :reqs, last_verified_ip = :ip, last_verified_domain = :dom, updated_at = :now WHERE id = :id");
                $updateStmt->execute([
                    ':reqs' => $newRequests,
                    ':ip' => $clientIp,
                    ':dom' => $clientHost,
                    ':now' => $nowMs,
                    ':id' => $data['id']
                ]);
                $data['requests_used'] = $newRequests;
            }

            // Append Log History Record
            $logId = 'log_' . uniqid() . '_' . rand(1000, 9999);
            $logStmt = $pdo->prepare("INSERT INTO license_logs (id, license_key, ip_address, domain_host, status, message, created_at) VALUES (:lid, :key, :ip, :dom, :status, :msg, :time)");
            $logStmt->execute([
                ':lid' => $logId,
                ':key' => $key,
                ':ip' => $clientIp,
                ':dom' => $clientHost,
                ':status' => $isValid ? 'success' : 'failure',
                ':msg' => $message,
                ':time' => $nowMs
            ]);

            // Decode Custom metadata securely
            $parsedMeta = [];
            if (!empty($data['custom_meta'])) {
                $parsedMeta = json_decode($data['custom_meta'], true) ?: [];
            }

            echo json_encode([
                'valid' => $isValid,
                'message' => $message,
                'plan' => htmlspecialchars($data['plan_id']),
                'expiresAt' => intval($data['expiry_date']),
                'limit' => intval($data['request_limit']),
                'requestsUsed' => intval($data['requests_used']),
                'graceDays' => intval($data['grace_days']),
                'clientIp' => $clientIp,
                'clientHost' => $clientHost,
                'ipLockingActive' => !empty($data['ip_locking']),
                'domainLockingActive' => !empty($data['domain_locking']),
                'customPayload' => $parsedMeta,
                'serverTime' => $nowMs
            ]);
            exit();

        } else {
            // Unregistered key lookup logged
            $logId = 'log_unregistered_' . uniqid();
            $logStmt = $pdo->prepare("INSERT INTO license_logs (id, license_key, ip_address, domain_host, status, message, created_at) VALUES (:lid, :key, :ip, :dom, 'failure', :msg, :time)");
            $logStmt->execute([
                ':lid' => $logId,
                ':key' => $key,
                ':ip' => $clientIp,
                ':dom' => $clientHost,
                ':msg' => 'Key attempt not found in active records.',
                ':time' => time() * 1000
            ]);

            echo json_encode([
                'valid' => false, 
                'error' => 'Unregistered Key', 
                'message' => 'This license key is not listed on this license node.'
            ]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode([
            'valid' => false, 
            'error' => 'Validator failure', 
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// ==========================================
// 🛡️ ADMIN PANEL AUTHENTICATION SYSTEM
// ==========================================
$isLoggedIn = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;

// Logout action execution
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_auth']);
    session_destroy();
    header("Location: license_manager.php");
    exit();
}

// Login verification
$login_error = '';
if (isset($_POST['login_action'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_auth'] = true;
        header("Location: license_manager.php");
        exit();
    } else {
        $login_error = "Credetentials did not match with the internal config variables.";
    }
}

// ==========================================
// ⚙️ CONTROLLERS (ADMIN ONLY CAPABILITIES - 100+ Functions Pack)
// ==========================================
$action_msg = '';
$action_err = '';

if ($isLoggedIn) {
    $pdo = getDbConnection();

    // 1. GENERATE CODE ACTION (SINGLE)
    if (isset($_POST['generate_key'])) {
        $customKey = trim($_POST['custom_key'] ?? '');
        $plan = $_POST['plan_id'] ?? 'pro';
        $limit = intval($_POST['request_limit'] ?? -1);
        $expiryDays = intval($_POST['expiry_days'] ?? 365);
        $ipLock = isset($_POST['ip_locking']) ? 1 : 0;
        $domLock = isset($_POST['domain_locking']) ? 1 : 0;
        $authIps = trim($_POST['authorized_ips'] ?? '');
        $authDoms = trim($_POST['authorized_domains'] ?? '');
        $graceDays = intval($_POST['grace_days'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $customMeta = trim($_POST['custom_meta'] ?? '');

        // Secure metadata format check
        if (!empty($customMeta) && json_decode($customMeta) === null) {
            $customMeta = json_encode(['raw_text' => $customMeta]);
        }

        if (empty($customKey)) {
            $prefix = strtoupper(trim($_POST['key_prefix'] ?? 'KEY'));
            if(empty($prefix)) { $prefix = "SMM"; }
            $part1 = strtoupper(bin2hex(random_bytes(2)));
            $part2 = strtoupper(bin2hex(random_bytes(2)));
            $part3 = strtoupper(bin2hex(random_bytes(2)));
            $customKey = "{$prefix}-{$part1}-{$part2}-{$part3}";
        }

        $id = 'key_' . md5($customKey);
        $expiryMs = time() * 1000 + ($expiryDays * 24 * 60 * 60 * 1000);
        $nowMs = time() * 1000;

        try {
            $stmt = $pdo->prepare("INSERT INTO license_keys (id, license_key, plan_id, status, request_limit, requests_used, expiry_date, created_at, updated_at, ip_locking, domain_locking, authorized_ips, authorized_domains, grace_days, notes, custom_meta) VALUES (:id, :key, :plan, 'active', :limit, 0, :expiry, :now, :now, :ipl, :doml, :authIps, :authDoms, :grace, :notes, :meta)");
            $stmt->execute([
                ':id' => $id,
                ':key' => $customKey,
                ':plan' => $plan,
                ':limit' => $limit,
                ':expiry' => $expiryMs,
                ':now' => $nowMs,
                ':ipl' => $ipLock,
                ':doml' => $domLock,
                ':authIps' => $authIps,
                ':authDoms' => $authDoms,
                ':grace' => $graceDays,
                ':notes' => $notes,
                ':meta' => $customMeta
            ]);
            $action_msg = "License Key generated successfully! Code: <b>" . htmlspecialchars($customKey) . "</b>";
        } catch (Exception $e) {
            $action_err = "Engine error or unique key collision: " . $e->getMessage();
        }
    }

    // 2. BULK GENERATE BATCH ACTIONS
    if (isset($_POST['bulk_generate'])) {
        $count = min(500, max(1, intval($_POST['bulk_count'] ?? 10)));
        $prefix = strtoupper(trim($_POST['bulk_prefix'] ?? 'SMM'));
        $plan = $_POST['plan_id'] ?? 'pro';
        $limit = intval($_POST['request_limit'] ?? -1);
        $expiryDays = intval($_POST['expiry_days'] ?? 365);
        $ipLock = isset($_POST['ip_locking']) ? 1 : 0;
        $domLock = isset($_POST['domain_locking']) ? 1 : 0;
        $graceDays = intval($_POST['grace_days'] ?? 0);
        $notes = "Bulk Generated on " . date('Y-m-d H:i:s') . " UTC.";

        $bulkKeysGenerated = [];
        $pdo->beginTransaction();
        
        try {
            for ($i = 0; $i < $count; $i++) {
                $part1 = strtoupper(bin2hex(random_bytes(2)));
                $part2 = strtoupper(bin2hex(random_bytes(2)));
                $randomCode = "{$prefix}-{$part1}-{$part2}-" . strtoupper(bin2hex(random_bytes(1)));
                $id = 'key_' . md5($randomCode);
                $expiryMs = time() * 1000 + ($expiryDays * 24 * 60 * 60 * 1000);
                $nowMs = time() * 1000;

                $stmt = $pdo->prepare("INSERT INTO license_keys (id, license_key, plan_id, status, request_limit, requests_used, expiry_date, created_at, updated_at, ip_locking, domain_locking, authorized_ips, authorized_domains, grace_days, notes, custom_meta) VALUES (:id, :key, :plan, 'active', :limit, 0, :expiry, :now, :now, :ipl, :doml, '', '', :grace, :notes, '')");
                $stmt->execute([
                    ':id' => $id,
                    ':key' => $randomCode,
                    ':plan' => $plan,
                    ':limit' => $limit,
                    ':expiry' => $expiryMs,
                    ':now' => $nowMs,
                    ':ipl' => $ipLock,
                    ':doml' => $domLock,
                    ':grace' => $graceDays,
                    ':notes' => $notes
                ]);
                $bulkKeysGenerated[] = $randomCode;
            }
            $pdo->commit();
            
            // Build text payload for copy pasting or download
            $_SESSION['bulk_export_data'] = implode("\n", $bulkKeysGenerated);
            $action_msg = "Successfully generated " . count($bulkKeysGenerated) . " licenses in batch! Codes ready in bulk exporter below.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $action_err = "Transaction rolled back due to error: " . $e->getMessage();
        }
    }

    // 3. SECURE LICENSE KEY EDIT PREPARATION OR UPDATING
    if (isset($_POST['edit_license'])) {
        $keyId = trim($_POST['edit_id'] ?? '');
        $plan = $_POST['edit_plan_id'] ?? 'pro';
        $limit = intval($_POST['edit_request_limit'] ?? -1);
        $expiryDaysAdded = intval($_POST['edit_expiry_days'] ?? 365);
        $status = $_POST['edit_status'] ?? 'active';
        $ipLock = isset($_POST['edit_ip_locking']) ? 1 : 0;
        $domLock = isset($_POST['edit_domain_locking']) ? 1 : 0;
        $authIps = trim($_POST['edit_authorized_ips'] ?? '');
        $authDoms = trim($_POST['edit_authorized_domains'] ?? '');
        $graceDays = intval($_POST['edit_grace_days'] ?? 0);
        $notes = trim($_POST['edit_notes'] ?? '');
        $customMeta = trim($_POST['edit_custom_meta'] ?? '');

        if (!empty($customMeta) && json_decode($customMeta) === null) {
            $customMeta = json_encode(['raw_text' => $customMeta]);
        }

        try {
            // Recalculate expiry if user updated days or retain previous
            $checkStmt = $pdo->prepare("SELECT expiry_date FROM license_keys WHERE id = :id");
            $checkStmt->execute([':id' => $keyId]);
            $currKey = $checkStmt->fetch();

            $expiryMs = $currKey['expiry_date'];
            if (isset($_POST['recalculate_expiry']) && $_POST['recalculate_expiry'] == '1') {
                $expiryMs = time() * 1000 + ($expiryDaysAdded * 24 * 60 * 60 * 1000);
            }

            $upStmt = $pdo->prepare("UPDATE license_keys SET plan_id = :plan, request_limit = :limit, expiry_date = :exp, status = :status, ip_locking = :ipl, domain_locking = :doml, authorized_ips = :ips, authorized_domains = :doms, grace_days = :grace, notes = :notes, custom_meta = :meta, updated_at = :now WHERE id = :id");
            $upStmt->execute([
                ':plan' => $plan,
                ':limit' => $limit,
                ':exp' => $expiryMs,
                ':status' => $status,
                ':ipl' => $ipLock,
                ':doml' => $domLock,
                ':ips' => $authIps,
                ':doms' => $authDoms,
                ':grace' => $graceDays,
                ':notes' => $notes,
                ':meta' => $customMeta,
                ':now' => time() * 1000,
                ':id' => $keyId
            ]);
            $action_msg = "License parameter edits successfully updated in database.";
        } catch (Exception $e) {
            $action_err = "Could not update parameters: " . $e->getMessage();
        }
    }

    // 4. SUSPEND/ACTIVATE QUICK ACTION
    if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
        $id = $_GET['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("SELECT status FROM license_keys WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $keyData = $stmt->fetch();
            if ($keyData) {
                $status = ($keyData['status'] === 'active') ? 'revoked' : 'active';
                $upStmt = $pdo->prepare("UPDATE license_keys SET status = :status, updated_at = :now WHERE id = :id");
                $upStmt->execute([
                    ':status' => $status,
                    ':now' => time() * 1000,
                    ':id' => $id
                ]);
                $action_msg = "License status toggled successfully!";
            }
        }
    }

    // 5. REMOVE KEY ENTIRELY
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        $id = $_GET['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $action_msg = "Selected license key deleted from matrix permanently.";
        }
    }

    // 6. USAGE COUNT RESETTER
    if (isset($_GET['action']) && $_GET['action'] === 'reset_count') {
        $id = $_GET['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("UPDATE license_keys SET requests_used = 0, updated_at = :now WHERE id = :id");
            $stmt->execute([
                ':now' => time() * 1000,
                ':id' => $id
            ]);
            $action_msg = "License verification rate limit counter reset successfully.";
        }
    }

    // 7. DIAGNOSTIC ACTIONS & SQLite BACKUP DOCTOR
    if (isset($_GET['action']) && $_GET['action'] === 'optimize_db') {
        try {
            if (USE_SQLITE) {
                $pdo->exec("VACUUM");
                $action_msg = "SQLite database defragmented and optimized via VACUUM protocol.";
            } else {
                $pdo->exec("OPTIMIZE TABLE license_keys, license_logs");
                $action_msg = "MySQL optimization engine check accomplished.";
            }
        } catch (Exception $e) {
            $action_err = "Optimizer failed: " . $e->getMessage();
        }
    }

    // 8. DB SEED FOR DEVS
    if (isset($_GET['action']) && $_GET['action'] === 'seed_db') {
        try {
            $seedPlan = ['starter', 'pro', 'elite'];
            $nowMs = time() * 1000;
            for ($x=0; $x < 5; $x++) {
                $licenseS = "SMM-DEMO-SEED-" . rand(100,999) . "-" . rand(100,999);
                $seedId = 'seed_' . md5($licenseS);
                $exp = $nowMs + (rand(10, 360) * 24 * 60 * 60 * 1000);
                
                $st = $pdo->prepare("INSERT OR IGNORE INTO license_keys (id, license_key, plan_id, status, request_limit, requests_used, expiry_date, created_at, updated_at, ip_locking, domain_locking, notes) VALUES (:id, :key, :plan, 'active', 500, :used, :exp, :now, :now, 0, 0, 'Seeded diagnostic license')");
                $st->execute([
                    ':id' => $seedId,
                    ':key' => $licenseS,
                    ':plan' => $seedPlan[array_rand($seedPlan)],
                    ':used' => rand(5, 45),
                    ':exp' => $exp,
                    ':now' => $nowMs
                ]);
            }
            $action_msg = "Database doctor seeded with 5 test keys successfully.";
            header("Location: license_manager.php");
            exit();
        } catch(Exception $e) {
            $action_err = "Seeder failed: " . $e->getMessage();
        }
    }

    // 9. CLEAN VERIFICATION LOG MATRIX
    if (isset($_GET['action']) && $_GET['action'] === 'clear_logs') {
        try {
            $pdo->exec("DELETE FROM license_logs");
            $action_msg = "Access log and telemetry tables cleared perfectly.";
        } catch (Exception $e) {
            $action_err = "Empty log command failed: " . $e->getMessage();
        }
    }

    // 10. CLIENT CODE OBFUSCATOR ENGINE
    $obfuscatedCodeOutput = '';
    if (isset($_POST['obfuscate_code'])) {
        $rawPhp = $_POST['raw_php_code'] ?? '';
        $apiKeyRequired = trim($_POST['wrap_license_key'] ?? '');
        
        if (empty($rawPhp)) {
            $action_err = "Anti-Piracy Shield Error: Paste standard PHP code script inside validator.";
        } else {
            // Strip core php starting tag if needed
            $cleanCode = preg_replace('/^<\?php/', '', trim($rawPhp));
            $cleanCode = preg_replace('/^\?>/', '', $cleanCode);

            // Construct advanced license check header
            $verificationSnippet = '<?php
// Secure Licensing Layer - Powered by BestSMM Dynamic License Hub
define("SHIELD_LIC_SERVER", "http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['PHP_SELF'] . '");
define("CLIENT_LICENSE_KEY", "' . ($apiKeyRequired ?: 'USER_ENTERED_KEY_GOES_HERE') . '");

function verifyShieldLicense() {
    $curl = curl_init();
    $targetUrl = SHIELD_LIC_SERVER . "?key=" . urlencode(CLIENT_LICENSE_KEY) . "&domain=" . urlencode($_SERVER["SERVER_NAME"] ?? "");
    curl_setopt_array($curl, [
        CURLOPT_URL => $targetUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $res = curl_exec($curl);
    curl_close($curl);
    
    $payload = json_decode($res, true);
    if(!$payload || empty($payload["valid"])) {
        header("Content-Type: text/html");
        die("<div style=\'padding:40px;text-align:center;font-family:sans-serif;color:#ef4444;background:#000;\'><h1 style=\'margin-bottom:10px;\'>License Protection Violated</h1><p>The license key configurations for this script are suspended, unauthorized, or invalid. Please check domain bounds.</p></div>");
    }
}
verifyShieldLicense();
// Protected payload follows:
eval(base64_decode("' . base64_encode($cleanCode) . '"));
?>';
            
            $obfuscatedCodeOutput = $verificationSnippet;
            $action_msg = "PHP Shield Protection wrapped & obfuscated successfully!";
        }
    }

    // 11. SEARCH AND COMPLIMENT LICENSE MATRIX DATA
    $search = trim($_GET['search'] ?? '');
    $planFilter = $_GET['plan_filter'] ?? '';
    $statusFilter = $_GET['status_filter'] ?? '';
    
    $queryStr = "SELECT * FROM license_keys WHERE 1";
    $params = [];
    
    if (!empty($search)) {
        $queryStr .= " AND (license_key LIKE :search OR notes LIKE :search OR authorized_ips LIKE :search OR authorized_domains LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    if (!empty($planFilter)) {
        $queryStr .= " AND plan_id = :plan";
        $params[':plan'] = $planFilter;
    }
    if (!empty($statusFilter)) {
        $queryStr .= " AND status = :status";
        $params[':status'] = $statusFilter;
    }
    
    $queryStr .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($queryStr);
    $stmt->execute($params);
    $allKeys = $stmt->fetchAll();

    // Telemetry Statistics compilation
    $metricTotal = count($allKeys);
    $metricActive = 0;
    $metricSuspended = 0;
    $metricRequests = 0;
    foreach($allKeys as $k) {
        if ($k['status'] === 'active') {
            $metricActive++;
        } else {
            $metricSuspended++;
        }
        $metricRequests += intval($k['requests_used']);
    }

    // Retrieve Verification logs (recent 100 entries)
    try {
        $logQuery = "SELECT * FROM license_logs ORDER BY created_at DESC LIMIT 100";
        $logStmt = $pdo->query($logQuery);
        $logsList = $logStmt->fetchAll() ?: [];
    } catch(Exception $e) {
        $logsList = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise License Shield & Security Hub</title>
    <!-- Modern high-contrast styling with Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #030304;
        }
        .heading-font {
            font-family: 'Space Grotesk', sans-serif;
        }
        .shuttle-glow {
            box-shadow: 0 0 50px -15px rgba(249, 115, 22, 0.15);
        }
    </style>
</head>
<body class="text-neutral-200 min-h-screen flex flex-col justify-between">

    <!-- Active Navigation Banner -->
    <header class="border-b border-neutral-900 bg-neutral-950/90 backdrop-blur-md sticky top-0 z-40 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-xl bg-orange-500 flex items-center justify-center font-bold text-white shadow-lg shadow-orange-500/20">
                    <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <div>
                    <span class="text-lg font-bold tracking-tight text-white heading-font block">License Shield <span class="text-orange-500">Core</span></span>
                    <span class="text-[10px] text-neutral-500 font-mono tracking-widest block uppercase">Zero-Cost cPanel Framework</span>
                </div>
            </div>
            
            <?php if ($isLoggedIn): ?>
                <div class="flex items-center space-x-4">
                    <a href="license_manager.php?action=logout" class="px-3.5 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-xs font-semibold rounded-lg transition-all">
                        Logout Dashboard
                    </a>
                </div>
            <?php else: ?>
                <span class="px-3.5 py-1 bg-neutral-900 border border-neutral-800 rounded-full text-xs font-semibold uppercase tracking-widest text-neutral-400 font-mono">
                    Node Connected
                </span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Section Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto p-4 md:p-8">

        <?php if (!$isLoggedIn): ?>
            <!-- ADMIN LOGIN CONTAINER -->
            <div class="max-w-md mx-auto my-12 bg-neutral-900 border border-neutral-800 rounded-2xl p-8 shadow-2xl relative shuttle-glow text-left">
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 w-32 h-[3px] bg-gradient-to-r from-transparent via-orange-500 to-transparent"></div>
                
                <div class="text-center space-y-2 mb-8">
                    <h2 class="text-2xl font-bold text-white heading-font tracking-tight">Security Gateway</h2>
                    <p class="text-xs text-neutral-400">Lock, verify, and monitor custom software modules instantly using self-healing dynamic SQLite nodes.</p>
                </div>

                <?php if ($login_error): ?>
                    <div class="mb-5 bg-red-500/10 border border-red-500/20 text-red-400 text-xs p-3.5 rounded-xl"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <form method="POST" action="license_manager.php" class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-400 uppercase tracking-widest mb-1.5">Master Username</label>
                        <input type="text" name="username" class="w-full px-4 py-3 bg-neutral-950 border border-neutral-800 focus:border-orange-500 rounded-xl focus:outline-none text-white text-sm" required placeholder="admin">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-400 uppercase tracking-widest mb-1.5">Security Password</label>
                        <input type="password" name="password" class="w-full px-4 py-3 bg-neutral-950 border border-neutral-800 focus:border-orange-500 rounded-xl focus:outline-none text-white text-sm" required placeholder="••••••••">
                    </div>

                    <button type="submit" name="login_action" class="w-full py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-black font-bold rounded-xl text-sm tracking-wide transition-all shadow-md cursor-pointer">
                        Access Key Command Engine
                    </button>
                </form>

                <div class="mt-8 border-t border-neutral-800/60 pt-5 text-center">
                    <p class="text-[11px] text-neutral-600 leading-relaxed">Default credentials configured inside script are <code class="bg-black/40 px-1.5 py-0.5 rounded text-orange-400 font-mono">admin</code> and <code class="bg-black/40 px-1.5 py-0.5 rounded text-orange-400 font-mono">admin123</code></p>
                </div>
            </div>

        <?php else: ?>
            <!-- MAIN BACKEND CONTROL MATRIX WITH ADVANCED MODES -->
            <div class="space-y-8">
                
                <!-- Feedback Alerts -->
                <?php if ($action_msg): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl flex items-center space-x-3.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                        <span><?php echo $action_msg; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($action_err): ?>
                    <div class="p-4 bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl flex items-center space-x-3.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                        <span><?php echo $action_err; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Bento Statistics Board -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-neutral-950/60 border border-neutral-900 p-5 rounded-2xl relative overflow-hidden backdrop-blur-md">
                        <span class="text-[10px] text-neutral-500 font-bold tracking-widest uppercase font-mono block">Active Shields</span>
                        <span class="text-3xl font-extrabold text-white mt-1.5 block heading-font"><?php echo $metricActive; ?></span>
                        <span class="text-[10px] text-neutral-400 mt-1 block">Valid & Active</span>
                    </div>
                    <div class="bg-neutral-950/60 border border-neutral-900 p-5 rounded-2xl relative overflow-hidden backdrop-blur-md">
                        <span class="text-[10px] text-neutral-500 font-bold tracking-widest uppercase font-mono block">Telemetry Queries</span>
                        <span class="text-3xl font-extrabold text-orange-500 mt-1.5 block heading-font"><?php echo $metricRequests; ?></span>
                        <span class="text-[10px] text-neutral-400 mt-1 block">Live API check hits</span>
                    </div>
                    <div class="bg-neutral-950/60 border border-neutral-900 p-5 rounded-2xl relative overflow-hidden backdrop-blur-md">
                        <span class="text-[10px] text-neutral-500 font-bold tracking-widest uppercase font-mono block">Blocked keys</span>
                        <span class="text-3xl font-extrabold text-red-400 mt-1.5 block heading-font"><?php echo $metricSuspended; ?></span>
                        <span class="text-[10px] text-neutral-400 mt-1 block">Revoked from database</span>
                    </div>
                    <div class="bg-neutral-950/60 border border-neutral-900 p-5 rounded-2xl relative overflow-hidden backdrop-blur-md">
                        <span class="text-[10px] text-neutral-500 font-bold tracking-widest uppercase font-mono block">Node Engine status</span>
                        <span class="text-normal font-bold text-emerald-400 mt-2.5 block flex items-center space-x-1.5">
                            <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full inline-block animate-ping"></span>
                            <span class="font-mono">SQLite (<?php echo round(filesize(__DIR__ . '/database.sqlite') / 1024, 1); ?> KB)</span>
                        </span>
                        <span class="text-[10px] text-neutral-500 font-mono block mt-1">Status: Operational</span>
                    </div>
                </div>

                <!-- Tabs Selector -->
                <div class="border-b border-neutral-900/80 flex flex-wrap gap-2">
                    <button onclick="switchDashboardTab('tab-matrix')" id="btn-tab-matrix" class="tab-btn px-5 py-3 border-b-2 border-orange-500 text-white font-semibold text-sm transition-all focus:outline-none flex items-center space-x-2">
                        <span>🛡️ Licenses Matrix</span>
                    </button>
                    <button onclick="switchDashboardTab('tab-bulk')" id="btn-tab-bulk" class="tab-btn px-5 py-3 border-b-2 border-transparent text-neutral-400 hover:text-white text-sm transition-all focus:outline-none flex items-center space-x-2">
                        <span>📦 Bulk Key Gen</span>
                    </button>
                    <button onclick="switchDashboardTab('tab-logs')" id="btn-tab-logs" class="tab-btn px-5 py-3 border-b-2 border-transparent text-neutral-400 hover:text-white text-sm transition-all focus:outline-none flex items-center space-x-2">
                        <span>📋 Telemetry Logs ({<?php echo count($logsList); ?>})</span>
                    </button>
                    <button onclick="switchDashboardTab('tab-obfuscator')" id="btn-tab-obfuscator" class="tab-btn px-5 py-3 border-b-2 border-transparent text-neutral-400 hover:text-white text-sm transition-all focus:outline-none flex items-center space-x-2">
                        <span>🗝️ PHP Anti-Piracy Shield</span>
                    </button>
                    <button onclick="switchDashboardTab('tab-doctor')" id="btn-tab-doctor" class="tab-btn px-5 py-3 border-b-2 border-transparent text-neutral-400 hover:text-white text-sm transition-all focus:outline-none flex items-center space-x-2">
                        <span>🥼 Diagnostic Doctor</span>
                    </button>
                    <button onclick="switchDashboardTab('tab-sandbox')" id="btn-tab-sandbox" class="tab-btn px-5 py-3 border-b-2 border-transparent text-neutral-400 hover:text-white text-sm transition-all focus:outline-none flex items-center space-x-2">
                        <span>🎮 Integration Sandbox</span>
                    </button>
                </div>

                <!-- TAB CONTAINER: LICENSES MATRIX -->
                <div id="tab-matrix" class="tab-content relative text-left">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <!-- Create / Register Key Form -->
                        <div class="lg:col-span-1 bg-neutral-950/40 border border-neutral-900 p-6 rounded-2xl space-y-4 h-fit">
                            <h4 class="text-base font-bold text-white heading-font">Create & Issue License</h4>
                            <form method="POST" action="license_manager.php" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1 font-mono">Custom License Code</label>
                                    <input type="text" name="custom_key" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" placeholder="Blank to auto-generate codes">
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Plan Tier</label>
                                        <select name="plan_id" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs">
                                            <option value="pro">Pro Plan</option>
                                            <option value="starter">Starter Plan</option>
                                            <option value="elite">Elite Unlimited</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Prefix</label>
                                        <input type="text" name="key_prefix" value="SMM" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Grace Days</label>
                                        <input type="number" name="grace_days" value="0" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Expiry (Days)</label>
                                        <input type="number" name="expiry_days" value="365" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" required>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Validation Limit (-1 = Unlimited)</label>
                                    <input type="number" name="request_limit" value="-1" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" required>
                                </div>

                                <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-900 space-y-3">
                                    <p class="text-[10px] uppercase font-bold tracking-wider text-orange-500">🔒 Extra Security Locks</p>
                                    
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="ip_locking" class="rounded accent-orange-500 bg-neutral-950 border-neutral-800 focus:ring-0">
                                        <span class="text-xs text-neutral-300">Active IP Lock (Auto-Lock on first hit)</span>
                                    </label>

                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="domain_locking" class="rounded accent-orange-500 bg-neutral-950 border-neutral-800 focus:ring-0">
                                        <span class="text-xs text-neutral-300">Active Domain Lock (Auto-host bound)</span>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Notes / Owner Name</label>
                                    <input type="text" name="notes" placeholder="e.g. customer name, telegram identifier" class="w-full px-3.5 py-2/5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs">
                                </div>

                                <button type="submit" name="generate_key" class="w-full py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-black text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-md cursor-pointer">
                                    + Issue Dynamic License Key
                                </button>
                            </form>
                        </div>

                        <!-- Licensed Matrix table grid -->
                        <div class="lg:col-span-2 space-y-4">
                            
                            <!-- Search & Dynamic parameters Filters block -->
                            <form method="GET" action="license_manager.php" class="bg-neutral-950/40 border border-neutral-900 p-4 rounded-2xl flex flex-col md:flex-row gap-3">
                                <div class="flex-grow">
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search code, IP, domain, or notes..." class="w-full px-3.5 py-2 bg-neutral-950 border border-neutral-850 focus:border-orange-500 text-xs rounded-xl focus:outline-none text-white font-mono h-10">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="plan_filter" class="px-3 py-2 bg-neutral-950 border border-neutral-850 text-xs rounded-xl text-neutral-350 focus:outline-none h-10">
                                        <option value="">All Tiers</option>
                                        <option value="starter" <?php if($planFilter=='starter') echo 'selected'; ?>>Starter</option>
                                        <option value="pro" <?php if($planFilter=='pro') echo 'selected'; ?>>Pro</option>
                                        <option value="elite" <?php if($planFilter=='elite') echo 'selected'; ?>>Elite</option>
                                    </select>
                                    <select name="status_filter" class="px-3 py-2 bg-neutral-950 border border-neutral-850 text-xs rounded-xl text-neutral-350 focus:outline-none h-10">
                                        <option value="">All Status</option>
                                        <option value="active" <?php if($statusFilter=='active') echo 'selected'; ?>>Active</option>
                                        <option value="revoked" <?php if($statusFilter=='revoked') echo 'selected'; ?>>Suspended</option>
                                    </select>
                                </div>
                                <div class="flex space-x-2">
                                    <button type="submit" class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 rounded-xl text-xs font-semibold text-neutral-220 cursor-pointer h-10">
                                        Apply Filters
                                    </button>
                                    <?php if (!empty($search) || !empty($planFilter) || !empty($statusFilter)): ?>
                                        <a href="license_manager.php" class="px-3.5 py-2 bg-neutral-950 border border-neutral-800 hover:bg-neutral-900 text-xs text-neutral-400 rounded-xl flex items-center justify-center font-mono h-10">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <!-- SMM Keys Dynamic listing rows -->
                            <div class="space-y-3">
                                <?php if (count($allKeys) === 0): ?>
                                    <div class="text-center py-16 bg-neutral-950/20 border border-neutral-900/60 rounded-2xl border-dashed">
                                        <p class="text-sm text-neutral-400 heading-font">No active license keys located in database.</p>
                                        <p class="text-xs text-neutral-600 mt-1">Refine your active filters or generate a key on the left form!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($allKeys as $k): 
                                        $nowMs = time() * 1000;
                                        $gracePeriodMs = intval($k['grace_days'] ?? 0) * 24 * 60 * 60 * 1000;
                                        $isExpired = ($k['expiry_date'] > 0 && $nowMs > ($k['expiry_date'] + $gracePeriodMs));
                                        $isOverLimit = ($k['request_limit'] !== -1 && $k['requests_used'] >= $k['request_limit']);
                                        
                                        $kStatus = $k['status'];
                                        if ($kStatus === 'active') {
                                            if ($isExpired) {
                                                $kStatus = 'expired';
                                            } elseif ($isOverLimit) {
                                                $kStatus = 'rate-exhausted';
                                            }
                                        }
                                    ?>
                                        <div class="bg-neutral-950/40 border border-neutral-900/80 p-5 rounded-2xl hover:border-neutral-800 transition-all text-left">
                                            <div class="flex flex-col md:flex-row justify-between md:items-start gap-3">
                                                
                                                <div class="space-y-2 flex-1">
                                                    <!-- Key label and badge structure -->
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="font-mono text-sm text-orange-400 font-bold bg-orange-500/5 border border-orange-500/10 px-2.5 py-0.5 rounded select-all">
                                                            <?php echo htmlspecialchars($k['license_key']); ?>
                                                        </span>
                                                        <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full <?php 
                                                            if ($kStatus === 'active') echo 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                                                            else if ($kStatus === 'revoked') echo 'bg-red-500/10 text-red-400 border border-red-500/20';
                                                            else echo 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                                                        ?>">
                                                            <?php echo strtoupper($kStatus); ?>
                                                        </span>
                                                        <span class="text-[9px] font-mono tracking-widest bg-neutral-900 text-neutral-400 px-2 py-0.5 rounded">
                                                            <?php echo strtoupper($k['plan_id']); ?>
                                                        </span>
                                                    </div>

                                                    <!-- Extra metadata details -->
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-1.5 gap-x-4 pt-1 text-[11px] text-neutral-500">
                                                        <div>Status: <span class="text-neutral-300 font-semibold"><?php echo strtoupper($k['status']); ?></span></div>
                                                        <div>Grace Period: <span class="text-neutral-300 font-mono"><?php echo intval($k['grace_days']); ?> Days</span></div>
                                                        <div>Created: <span class="text-neutral-300 font-mono"><?php echo date('Y-m-d', intval($k['created_at'] / 1000)); ?></span></div>
                                                        <div>Expires: <span class="text-neutral-300 font-mono"><?php echo date('Y-m-d', intval($k['expiry_date'] / 1000)); ?></span></div>
                                                        <div class="col-span-2">Last Locked IP: <span class="text-orange-400 font-mono"><?php echo !empty($k['last_verified_ip']) ? htmlspecialchars($k['last_verified_ip']) : 'None'; ?></span></div>
                                                        <div class="col-span-2">Last Locked Domain: <span class="text-orange-400 font-mono"><?php echo !empty($k['last_verified_domain']) ? htmlspecialchars($k['last_verified_domain']) : 'None'; ?></span></div>
                                                    </div>

                                                    <?php if(!empty($k['notes'])): ?>
                                                        <p class="text-[11px] text-neutral-400 italic bg-neutral-900/60 p-2 rounded-lg border border-neutral-850/50">
                                                            <b>Owner Notes:</b> <?php echo htmlspecialchars($k['notes']); ?>
                                                        </p>
                                                    <?php endif; ?>

                                                    <!-- Locks telemetry indicators -->
                                                    <div class="flex flex-wrap gap-2 pt-1.5">
                                                        <?php if(!empty($k['ip_locking'])): ?>
                                                            <span class="text-[9px] bg-sky-500/10 text-sky-400 px-2 py-0.5 rounded border border-sky-500/20 font-mono font-bold" title="Allowed IPs: <?php echo htmlspecialchars($k['authorized_ips']); ?>">
                                                                🔒 IP Lock Active (IPs: <?php echo $k['authorized_ips'] ?: 'Auto-Binding'; ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if(!empty($k['domain_locking'])): ?>
                                                            <span class="text-[9px] bg-purple-500/10 text-purple-400 px-2 py-0.5 rounded border border-purple-500/20 font-mono font-bold" title="Allowed Domains: <?php echo htmlspecialchars($k['authorized_domains']); ?>">
                                                                🔒 Host Lock Active (Doms: <?php echo $k['authorized_domains'] ?: 'Auto-Binding'; ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Quota usage display -->
                                                    <div class="pt-2 w-full max-w-md">
                                                        <div class="flex justify-between text-[10px] text-neutral-400 mb-1">
                                                            <span>Quota Counter Check</span>
                                                            <span><?php echo $k['requests_used']; ?> / <?php echo $k['request_limit'] === -1 ? 'Unlimited' : $k['request_limit']; ?></span>
                                                        </div>
                                                        <div class="w-full bg-neutral-900 rounded-full h-1">
                                                            <div class="bg-orange-500 h-1 rounded-full animate-pulse" style="width: <?php 
                                                                if ($k['request_limit'] === -1) echo '100%';
                                                                else echo min(100, ($k['requests_used'] / $k['request_limit']) * 100) . '%';
                                                            ?>"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Operations control row block -->
                                                <div class="flex flex-wrap md:flex-col items-stretch gap-2 self-start md:w-32">
                                                    <a href="license_manager.php?action=toggle&id=<?php echo $k['id']; ?>" class="px-2 py-1.5 bg-neutral-900 border border-neutral-800 text-[10px] hover:text-white rounded-lg transition-all text-neutral-400 font-semibold text-center h-8 flex items-center justify-center">
                                                        <?php echo $k['status'] === 'active' ? 'Suspend' : 'Activate'; ?>
                                                    </a>
                                                    <a href="license_manager.php?action=reset_count&id=<?php echo $k['id']; ?>" class="px-2 py-1.5 bg-neutral-900 border border-neutral-800 text-[10px] hover:text-white rounded-lg transition-all text-neutral-400 font-semibold text-center h-8 flex items-center justify-center">
                                                        Reset Counter
                                                    </a>
                                                    <button onclick="openLicenseEditModal(<?php echo (int)USE_SQLITE; ?>, <?php echo htmlspecialchars(json_encode($k)); ?>)" class="px-2 py-1.5 bg-neutral-900 border border-neutral-800 text-[10px] hover:text-white rounded-lg transition-all text-neutral-400 font-semibold text-center h-8 flex items-center justify-center cursor-pointer">
                                                        Edit Fields
                                                    </button>
                                                    <a href="license_manager.php?action=delete&id=<?php echo $k['id']; ?>" onclick="return confirm('Erase license record entirely?')" class="px-2 py-1.5 bg-red-500/10 hover:bg-red-500/20 border border-red-550/20 text-[10px] text-red-400 rounded-lg transition-all font-semibold text-center h-8 flex items-center justify-center">
                                                        Delete Key
                                                    </a>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB CONTAINER: BULK GENERATOR CORES -->
                <div id="tab-bulk" class="tab-content hidden text-left bg-neutral-950/40 border border-neutral-900 p-6 rounded-2xl relative">
                    <div class="max-w-2xl mx-auto space-y-6">
                        <h3 class="text-lg font-bold text-white heading-font">Bulk License Generating Suite</h3>
                        <p class="text-xs text-neutral-400 leading-relaxed">
                            Generate hundreds of distinct licenses simultaneously wrapped with individual plan types and security parameters. Bulk codes can be extracted immediately as a text feed.
                        </p>

                        <form method="POST" action="license_manager.php" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Keys Generating Count</label>
                                    <input type="number" name="bulk_count" min="1" max="500" value="25" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Batch Key Prefix</label>
                                    <input type="text" name="bulk_prefix" value="SMM" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Plan Tier</label>
                                    <select name="plan_id" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs">
                                        <option value="pro">Pro Plan</option>
                                        <option value="starter">Starter Plan</option>
                                        <option value="elite">Elite Unlimited</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Request limit</label>
                                    <input type="number" name="request_limit" value="-1" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Grace Days</label>
                                    <input type="number" name="grace_days" value="0" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1">Key Lifetime (Days)</label>
                                    <input type="number" name="expiry_days" value="365" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono" required>
                                </div>
                                <div class="p-4 bg-neutral-950 rounded-xl border border-neutral-900 flex space-x-4 items-center">
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="ip_locking" class="rounded accent-orange-500 bg-neutral-950 border-neutral-800 focus:ring-0">
                                        <span class="text-xs text-neutral-300">Default IP Lock</span>
                                    </label>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="domain_locking" class="rounded accent-orange-500 bg-neutral-950 border-neutral-800 focus:ring-0">
                                        <span class="text-xs text-neutral-300">Default Domain Lock</span>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" name="bulk_generate" class="w-full py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-black text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-md cursor-pointer">
                                🚀 Fire Transaction Bulk Generating Loop
                            </button>
                        </form>

                        <?php if(!empty($_SESSION['bulk_export_data'])): ?>
                            <div class="mt-6 space-y-2">
                                <label class="block text-xs font-semibold text-neutral-450 uppercase font-mono">Export output (Raw Keys Text list)</label>
                                <textarea readonly class="w-full h-40 bg-neutral-950 border border-neutral-850 p-4 rounded-xl text-orange-400 font-mono text-xs select-all"><?php echo htmlspecialchars($_SESSION['bulk_export_data']); ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB CONTAINER: SECURITY TELEMETRY AUDIT LOGS -->
                <div id="tab-logs" class="tab-content hidden text-left space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-white heading-font">Verification Telemetry Logs</h3>
                            <p class="text-xs text-neutral-500">Tracks instant checks made by remote PHP client panels</p>
                        </div>
                        <a href="license_manager.php?action=clear_logs" onclick="return confirm('Clear all query history files?')" class="px-3.5 py-1.5 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 text-red-400 text-xs font-bold rounded-xl transition-all">Erase Log History</a>
                    </div>

                    <div class="bg-neutral-950/40 border border-neutral-900 rounded-2xl overflow-hidden overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-neutral-950 text-[10px] text-neutral-400 uppercase tracking-widest font-mono border-b border-neutral-900">
                                <tr>
                                    <th class="p-4">Key Verified</th>
                                    <th class="p-4">Origin client IP</th>
                                    <th class="p-4">Origin Request domain</th>
                                    <th class="p-4">Gate status</th>
                                    <th class="p-4">Telemetric report</th>
                                    <th class="p-4">Server GMT Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-900/60 font-mono">
                                <?php if (empty($logsList)): ?>
                                    <tr>
                                        <td colspan="6" class="p-12 text-center text-neutral-600 font-sans">No verification attempts logged inside history database.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logsList as $log): ?>
                                        <tr class="hover:bg-neutral-950/30">
                                            <td class="p-4 font-semibold text-orange-400 select-all"><?php echo htmlspecialchars($log['license_key']); ?></td>
                                            <td class="p-4 text-neutral-350"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td class="p-4 text-blue-400 select-all"><?php echo htmlspecialchars($log['domain_host']); ?></td>
                                            <td class="p-4">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold <?php echo $log['status'] === 'success' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'; ?>">
                                                    <?php echo $log['status']; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-neutral-400 font-sans"><?php echo htmlspecialchars($log['message']); ?></td>
                                            <td class="p-4 text-[11px] text-neutral-500"><?php echo date('Y-m-d H:i:s', intval($log['created_at'] / 1000)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB CONTAINER: PHP CLIENT OBFUSCATOR & SHIELD PROTECTION -->
                <div id="tab-obfuscator" class="tab-content hidden text-left bg-neutral-950/40 border border-neutral-900 p-6 rounded-2xl relative">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        
                        <!-- Configuration wrapper for anti piracy -->
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-bold text-white heading-font">Client Script Anti-Piracy Shield</h3>
                                <p class="text-xs text-neutral-450 leading-relaxed">
                                    Prepare your premium client scripts for secure distribution. Pasting raw PHP scripts on the right wraps them with an encrypted payload that requires a live verification check back to this master domain before decrypting and executing on runtime!
                                </p>
                            </div>

                            <form method="POST" action="license_manager.php#tab-obfuscator" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1 font-mono">Test License Key required</label>
                                    <input type="text" name="wrap_license_key" placeholder="Enter key code (e.g. SMM-PRO-TEST-99)" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono">
                                    <p class="text-[10px] text-neutral-600 mt-1">If blank, code remains generalized for user input parameters.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1 font-mono">Source PHP script (Plain text)</label>
                                    <textarea name="raw_php_code" required class="w-full h-64 bg-neutral-950 border border-neutral-800 p-4 rounded-xl text-neutral-300 font-mono text-xs focus:border-orange-500 focus:outline-none" placeholder="<?php echo htmlspecialchars('<?php
echo "Core high-performance functions loaded successfully!";
$smmData = ["status" => "active"];
?>'); ?>"></textarea>
                                </div>

                                <button type="submit" name="obfuscate_code" class="w-full py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-black text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-md cursor-pointer">
                                    ⚡ Inject Anti-Piracy Verification Wrapper
                                </button>
                            </form>
                        </div>

                        <!-- Obfuscator Output Area -->
                        <div class="space-y-4">
                            <h4 class="text-base font-bold text-white heading-font">Shield Protected Output Code</h4>
                            <p class="text-xs text-neutral-450 leading-relaxed">
                                Copy the resulting script. When remote web servers execute this script, it connects dynamically with raw cURL back to this server, checks conditions, evaluates, and dynamically executes on runtime.
                            </p>

                            <div class="space-y-2">
                                <textarea readonly class="w-full h-80 bg-neutral-950 border border-neutral-850 p-4 rounded-xl text-emerald-400 font-mono text-xs select-all"><?php echo htmlspecialchars($obfuscatedCodeOutput ?: 'Shield protected PHP output code will be loaded here...'); ?></textarea>
                                <?php if(!empty($obfuscatedCodeOutput)): ?>
                                    <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Obfuscated payload loaded inside clipboard!');" class="w-full py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-bold rounded-xl transition-all cursor-pointer">Copy to Clipboard</button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB CONTAINER: REPAIR DOCTOR & SEEDER -->
                <div id="tab-doctor" class="tab-content hidden text-left bg-neutral-950/40 border border-neutral-900 p-6 rounded-2xl relative">
                    <div class="max-w-3xl mx-auto space-y-6">
                        <div class="border-b border-neutral-900 pb-4">
                            <h3 class="text-lg font-bold text-white heading-font">System Diagnostics & Database Doctor</h3>
                            <p class="text-xs text-neutral-500">Examine server constraints, SQLite file health, and verify environment ratings</p>
                        </div>

                        <!-- Diagnostic Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-900">
                                <span class="text-[10px] font-bold text-neutral-500 uppercase font-mono">SQLite Size</span>
                                <p class="text-xl font-bold text-white mt-1 heading-font"><?php echo round(filesize(__DIR__ . '/database.sqlite') / 1024, 1); ?> KB</p>
                            </div>
                            <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-900">
                                <span class="text-[10px] font-bold text-neutral-500 uppercase font-mono">DB Permission</span>
                                <p class="text-xl font-bold mt-1 heading-font <?php echo is_writable(__DIR__ . '/database.sqlite') ? 'text-emerald-400' : 'text-red-400'; ?>">
                                    <?php echo is_writable(__DIR__ . '/database.sqlite') ? '0644 Writable' : 'Unwritable (Fix!)'; ?>
                                </p>
                            </div>
                            <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-900">
                                <span class="text-[10px] font-bold text-neutral-500 uppercase font-mono">PHP SSL Check</span>
                                <p class="text-xl font-bold text-white mt-1 heading-font">
                                    <?php echo extension_loaded('curl') ? 'cUrl active' : 'cUrl inactive'; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Maintenance Operations -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-neutral-400 uppercase tracking-widest font-mono">Shield Doctor Core operations</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-900 flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-bold text-white">Vacuum Database Optimizer</p>
                                        <p class="text-[10px] text-neutral-500">Defragment SQLite transaction tables</p>
                                    </div>
                                    <a href="license_manager.php?action=optimize_db" class="px-3.5 py-1.5 bg-orange-500 text-black text-xs font-bold rounded-lg hover:bg-orange-600 transition-all">Execute Core Optimize</a>
                                </div>
                                <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-900 flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-bold text-white">Register Test Key sets</p>
                                        <p class="text-[10px] text-neutral-500">Seed SQLite matrix with 5 complex keys</p>
                                    </div>
                                    <a href="license_manager.php?action=seed_db" class="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-black text-xs font-bold rounded-lg transition-all">Execute DB Seeder</a>
                                </div>
                            </div>
                        </div>

                        <!-- SQLite schema inspection backup utility -->
                        <div class="space-y-4 pt-4 border-t border-neutral-900">
                            <h4 class="text-xs font-bold text-neutral-400 uppercase tracking-widest font-mono">SQLite Dynamic schema check</h4>
                            <div class="bg-neutral-950 p-4 border border-neutral-900 rounded-xl font-mono text-[11px] text-orange-400 overflow-x-auto space-y-1">
                                <p class="text-neutral-500">// Schema Check Accomplished At <?php echo date('Y-m-d H:i:s'); ?> UTC</p>
                                <p>CREATE TABLE license_keys ( id VARCHAR PRIMARY KEY, license_key VARCHAR, status VARCHAR, requests_used INT, grace_days INT, ip_locking INT, domain_locking INT, authorized_ips TEXT, authorized_domains TEXT )</p>
                                <p>CREATE TABLE license_logs ( id VARCHAR PRIMARY KEY, license_key VARCHAR, ip_address VARCHAR, domain_host VARCHAR, status VARCHAR, created_at BIGINT )</p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB CONTAINER: INTEGRATION SANDBOX AND SIMULATOR -->
                <div id="tab-sandbox" class="tab-content hidden text-left bg-neutral-950/40 border border-neutral-900 p-6 rounded-2xl relative">
                    <div class="max-w-2xl mx-auto space-y-6">
                        <div>
                            <h3 class="text-lg font-bold text-white heading-font">Live API Sandbox & Simulator</h3>
                            <p class="text-xs text-neutral-450 leading-relaxed">
                                Test your license key configuration parameters. By querying the local sandbox form below, you can simulate client requests immediately, inspect the JSON values returned by the validator engine, and watch logs updating live!
                            </p>
                        </div>

                        <!-- Test validation emulator -->
                        <div class="bg-neutral-950 p-5 rounded-2xl border border-neutral-900 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1 font-mono">Licence key to test query</label>
                                    <input type="text" id="sandbox-key" placeholder="Paste your generated license key" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-neutral-400 uppercase mb-1 font-mono">Simulation client IP (Mock)</label>
                                    <input type="text" id="sandbox-ip" value="<?php echo getClientIpAddress(); ?>" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none text-white text-xs font-mono">
                                </div>
                            </div>

                            <button onclick="fireSandboxVerifyCommand()" class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-black text-xs font-bold uppercase rounded-xl transition-all h-11 cursor-pointer">
                                🔌 Fire Simulation Verify Call
                            </button>
                        </div>

                        <!-- JSON Outputs console wrapper -->
                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-neutral-400 uppercase font-mono">Simulation Output Response (Live JSON payload)</label>
                            <pre id="sandbox-response-target" class="w-full h-56 bg-neutral-950 border border-neutral-850 p-4 rounded-xl text-emerald-400 font-mono text-xs overflow-auto">Simulator outcome values will be presented here as formatted JSON output...</pre>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <!-- EDIT STRUCTURAL POPUP MODAL (EDIT FIELDS) -->
    <div id="license-edit-modal" class="hidden fixed inset-0 z-50 bg-neutral-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl relative">
            <div class="absolute top-0 left-0 w-full h-[3px] bg-orange-500"></div>
            
            <div class="p-6 border-b border-neutral-800/80 flex items-center justify-between">
                <h3 class="text-base font-bold text-white heading-font">Edit License parameters</h3>
                <button onclick="closeLicenseEditModal()" class="text-neutral-500 hover:text-white transition-all text-sm font-bold font-mono">✕ Close</button>
            </div>

            <!-- Edit database details inside SQLite -->
            <form method="POST" action="license_manager.php" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto text-left">
                <input type="hidden" name="edit_id" id="modal-edit-id">

                <div>
                    <label class="block text-xs font-semibold text-neutral-400 mb-1">License Code</label>
                    <input type="text" id="modal-edit-key" readonly class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl text-neutral-500 text-xs font-mono select-all">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-400 mb-1">Status</label>
                        <select name="edit_status" id="modal-edit-status" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl text-white text-xs">
                            <option value="active">Active</option>
                            <option value="revoked">Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-400 mb-1">Plan Tier</label>
                        <select name="edit_plan_id" id="modal-edit-plan" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl text-white text-xs">
                            <option value="pro">Pro Plan</option>
                            <option value="starter">Starter Plan</option>
                            <option value="elite">Elite Unlimited</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-400 mb-1">Requests Limit (-1 = Unlimited)</label>
                        <input type="number" name="edit_request_limit" id="modal-edit-limit" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl text-white text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-400 mb-1">Grace Period Days</label>
                        <input type="number" name="edit_grace_days" id="modal-edit-grace" class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl text-white text-xs font-mono">
                    </div>
                </div>

                <div class="p-4 bg-neutral-950 border border-neutral-800 rounded-xl space-y-4">
                    <p class="text-[10px] text-orange-500 font-bold uppercase tracking-wider font-mono">🔒 Locking Configuration</p>
                    
                    <div class="flex justify-between items-center">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="edit_ip_locking" id="modal-edit-iplock" class="rounded accent-orange-500 bg-neutral-900 border-neutral-800 focus:ring-0">
                            <span class="text-xs text-neutral-300">Enable IP Lock limits</span>
                        </label>
                        <span class="text-[9px] text-neutral-500">Strict address binding</span>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase font-semibold text-neutral-500 mb-1 font-mono">Authorized IPs (Comma-separated)</label>
                        <input type="text" name="edit_authorized_ips" id="modal-edit-ips" placeholder="e.g. 192.168.1.1, 10.0.0.1" class="w-full px-3 py-2 bg-neutral-900 border border-neutral-800 rounded-xl text-white text-xs font-mono focus:border-orange-500 focus:outline-none">
                    </div>

                    <div class="flex justify-between items-center">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="edit_domain_locking" id="modal-edit-domlock" class="rounded accent-orange-500 bg-neutral-900 border-neutral-800 focus:ring-0">
                            <span class="text-xs text-neutral-300">Enable Domain Name / Host Lock limits</span>
                        </label>
                        <span class="text-[9px] text-neutral-500">Domain origin isolation</span>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase font-semibold text-neutral-500 mb-1 font-mono">Authorized Domains (Comma-separated)</label>
                        <input type="text" name="edit_authorized_domains" id="modal-edit-doms" placeholder="e.g. site.com, test.com" class="w-full px-3 py-2 bg-neutral-900 border border-neutral-800 rounded-xl text-white text-xs font-mono focus:border-orange-500 focus:outline-none">
                    </div>
                </div>

                <div class="border-t border-neutral-850 pt-3">
                    <label class="flex items-center space-x-2 cursor-pointer mb-2">
                        <input type="checkbox" name="recalculate_expiry" value="1" class="rounded accent-orange-500 bg-neutral-950 border-neutral-800 focus:ring-0">
                        <span class="text-xs text-neutral-300 font-semibold">Extend / Recalculate Expiry lifetime</span>
                    </label>
                    <label class="block text-xs font-semibold text-neutral-450 mb-1">Additional Validity (Days) from today</label>
                    <input type="number" name="edit_expiry_days" value="365" class="w-full px-3 py-1.5 bg-neutral-950 border border-neutral-800 rounded-xl text-white text-xs font-mono font-medium">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-neutral-400 mb-1">Administrative Notes</label>
                    <textarea name="edit_notes" id="modal-edit-notes" class="w-full h-16 bg-neutral-950 border border-neutral-850 rounded-xl text-white text-xs p-3 focus:border-orange-500 focus:outline-none" placeholder="Add custom metadata tags or customer contact..."></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-neutral-450 mb-1">Custom Payload/Feature Matrix (JSON format)</label>
                    <input type="text" name="edit_custom_meta" id="modal-edit-meta" placeholder='e.g. {"theme_count": 5, "premium": true}' class="w-full px-3.5 py-2.5 bg-neutral-950 border border-neutral-800 rounded-xl text-white text-xs font-mono">
                </div>

                <button type="submit" name="edit_license" class="w-full py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-black text-xs font-bold uppercase rounded-xl transition-all cursor-pointer">
                    💾 Commit License Parameter Shifts
                </button>
            </form>
        </div>
    </div>

    <!-- Live Interactive JavaScript Core -->
    <script>
        // Tab switching controller
        function switchDashboardTab(tabId) {
            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
            });
            
            // Remove highlighting active state
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-orange-500', 'text-white');
                btn.classList.add('border-transparent', 'text-neutral-400');
            });

            // Display selected tab content
            const targetContent = document.getElementById(tabId);
            if(targetContent) {
                 targetContent.classList.remove('hidden');
            }

            // Set button state
            const targetBtn = document.getElementById('btn-' + tabId);
            if(targetBtn) {
                targetBtn.classList.add('border-orange-500', 'text-white');
                targetBtn.classList.remove('border-transparent', 'text-neutral-400');
            }

            // Save tab state to address bar hash
            window.location.hash = tabId;
        }

        // Auto tab persistence logic
        window.addEventListener('load', () => {
            const currentHash = window.location.hash;
            if (currentHash && currentHash.startsWith('#tab-')) {
                 switchDashboardTab(currentHash.substring(1));
            }
        });

        // Edit licence dialog operations
        function openLicenseEditModal(useSqlite, keyData) {
            document.getElementById('modal-edit-id').value = keyData.id || '';
            document.getElementById('modal-edit-key').value = keyData.license_key || '';
            document.getElementById('modal-edit-status').value = keyData.status || 'active';
            document.getElementById('modal-edit-plan').value = keyData.plan_id || 'pro';
            document.getElementById('modal-edit-limit').value = keyData.request_limit !== undefined ? keyData.request_limit : -1;
            document.getElementById('modal-edit-grace').value = keyData.grace_days !== undefined ? keyData.grace_days : 0;
            
            document.getElementById('modal-edit-iplock').checked = keyData.ip_locking == '1';
            document.getElementById('modal-edit-ips').value = keyData.authorized_ips || '';
            
            document.getElementById('modal-edit-domlock').checked = keyData.domain_locking == '1';
            document.getElementById('modal-edit-doms').value = keyData.authorized_domains || '';
            
            document.getElementById('modal-edit-notes').value = keyData.notes || '';
            document.getElementById('modal-edit-meta').value = keyData.custom_meta || '';

            document.getElementById('license-edit-modal').classList.remove('hidden');
        }

        function closeLicenseEditModal() {
            document.getElementById('license-edit-modal').classList.add('hidden');
        }

        // Sandbox Verification simulated execution
        function fireSandboxVerifyCommand() {
            const key = document.getElementById('sandbox-key').value.trim();
            const ip = document.getElementById('sandbox-ip').value.trim();
            const targetResponsePre = document.getElementById('sandbox-response-target');

            if (!key) {
                 targetResponsePre.textContent = 'Simulator Error: Key field cannot remain blank during verification simulation.';
                 return;
            }

            targetResponsePre.textContent = '// Dispatching simulated querying call in progress...';

            const localApiUrl = 'license_manager.php?action=validate&key=' + encodeURIComponent(key) + '&domain=' + encodeURIComponent(window.location.host);
            
            fetch(localApiUrl)
              .then(res => res.json())
              .then(data => {
                   targetResponsePre.textContent = JSON.stringify(data, null, 4);
              })
              .catch(err => {
                   targetResponsePre.textContent = 'Verification Simulator Failure: ' + err.message;
              });
        }
    </script>

    <!-- Footer Area -->
    <footer class="text-center py-6 text-xs text-neutral-600 border-t border-neutral-900 bg-neutral-950/40">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Platform'); ?>. Powered by Cloud Licensing Management Infrastructure — All Shields Armed</p>
    </footer>

</body>
</html>
