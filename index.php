<?php
/**
 * Premium Live License Activation Script
 * 
 * Upload this file as 'index.php' in your public_html folder.
 * It will instantly connect to your AI Studio hosted Licensing System.
 */

session_start();

// Define licensing server endpoints (built on Google AI Studio Cloud Run)
define('API_HOST', 'https://ais-pre-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app');
define('LICENSE_MANAGER_URL', 'https://ais-dev-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app');

// Function to validate license key via API cURL
function validateLicenseKey($key) {
    if (empty($key)) {
        return ['valid' => false, 'message' => 'Please enter a license key.'];
    }
    
    // Call the high-availability validator endpoint
    $apiUrl = API_HOST . "/api/validate-license?key=" . urlencode($key);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return [
            'valid' => false, 
            'message' => 'Connection Error to License Hub: ' . curl_error($ch)
        ];
    }
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (!$data) {
        return [
            'valid' => false, 
            'message' => 'Unable to decode response. Server might be updating, try again.'
        ];
    }
    
    return $data;
}

$error_message = '';
$success_message = '';

// Force Deactivate / Logout Action
if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
    unset($_SESSION['bestsmm_license_key']);
    unset($_SESSION['bestsmm_license_data']);
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle Form Submission
if (isset($_POST['activate_license'])) {
    $enteredKey = trim($_POST['license_key'] ?? '');
    
    if (empty($enteredKey)) {
        $error_message = 'License key field cannot be empty!';
    } else {
        $res = validateLicenseKey($enteredKey);
        
        if (!empty($res['valid'])) {
            $_SESSION['bestsmm_license_key'] = $enteredKey;
            $_SESSION['bestsmm_license_data'] = $res;
            $success_message = "License successfully validated! Welcome back to " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'our platform') . ".";
        } else {
            $error_message = isset($res['message']) ? $res['message'] : 'The entered license key is invalid, expired, or suspended.';
        }
    }
}

// Verify Session State
$is_valid = false;
$license_data = null;
if (isset($_SESSION['bestsmm_license_key'])) {
    $storedKey = $_SESSION['bestsmm_license_key'];
    $res = validateLicenseKey($storedKey);
    if (!empty($res['valid'])) {
        $is_valid = true;
        $license_data = $res;
    } else {
        // Stored key is no longer valid (e.g. deleted or deactivated remotely)
        unset($_SESSION['bestsmm_license_key']);
        unset($_SESSION['bestsmm_license_data']);
        $error_message = "Your active license session was revoked or expired remotely.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Activation'); ?> — License Management Activation</title>
    <!-- Tailwind CSS with custom styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #070708 0%, #111115 100%);
        }
        .glow-orange {
            box-shadow: 0 0 40px -10px rgba(249, 115, 22, 0.15);
        }
    </style>
</head>
<body class="text-neutral-200 min-h-screen flex flex-col justify-between">

    <!-- Header / Navbar -->
    <header class="border-b border-neutral-900 bg-neutral-950/80 backdrop-blur-md px-6 py-4 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center font-bold text-white shadow-lg shadow-orange-500/20">
                    L
                </div>
                <span class="text-lg font-bold tracking-tight text-white">License Activation</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'smm-panel'); ?>
                </span>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-lg w-full mx-auto p-4 flex flex-col justify-center my-12">
        <div class="bg-neutral-900 border border-neutral-800 rounded-3xl overflow-hidden glow-orange shadow-2xl p-8 relative">
            
            <!-- Branding Accent -->
            <div class="absolute top-0 left-1/2 transform -translate-x-1/2 w-32 h-[2px] bg-gradient-to-r from-transparent via-orange-500 to-transparent"></div>

            <?php if (!$is_valid): ?>
                <!-- LICENSE FORM SCREEN -->
                <div class="space-y-6">
                    <div class="text-center space-y-2">
                        <div class="inline-flex w-14 h-14 bg-orange-500/10 text-orange-500 rounded-2xl items-center justify-center border border-orange-500/20 mb-2">
                            <!-- Shield/Lock Icon -->
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-white tracking-tight">Activation Required</h2>
                        <p class="text-xs text-neutral-400">
                            Please provide a valid license key to access the panels, tools and operations.
                        </p>
                    </div>

                    <!-- Flash messages -->
                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl flex items-start space-x-3 text-sm text-red-400">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-2">License Key</label>
                            <input 
                                type="text" 
                                name="license_key" 
                                placeholder="XXXXX-XXXXX-XXXXX-XXXXX" 
                                value="<?php echo htmlspecialchars($_POST['license_key'] ?? ''); ?>"
                                class="w-full px-4 py-3.5 bg-neutral-950 text-white border border-neutral-800 rounded-xl focus:border-orange-500 focus:outline-none transition-all placeholder:text-neutral-700 font-mono text-center tracking-wide"
                                required
                            />
                        </div>

                        <button 
                            type="submit" 
                            name="activate_license"
                            class="w-full py-4 px-4 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white rounded-xl font-semibold tracking-wide transition-all shadow-lg hover:shadow-orange-500/10 cursor-pointer"
                        >
                            Verify & Activate
                        </button>
                    </form>

                    <div class="border-t border-neutral-800/60 my-6"></div>

                    <!-- Direct Dash Navigation Assistance -->
                    <div class="space-y-3">
                        <div class="text-center">
                            <span class="text-xs text-neutral-500">Don't have a functional License Key yet?</span>
                        </div>
                        
                        <a 
                            href="<?php echo LICENSE_MANAGER_URL; ?>" 
                            target="_blank"
                            class="w-full inline-flex items-center justify-center py-3.5 px-4 bg-neutral-950 hover:bg-neutral-800 border border-neutral-800 hover:border-neutral-700 text-sm font-medium rounded-xl text-neutral-300 transition-all cursor-pointer"
                        >
                            <span>Open License Manager Dashboard</span>
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                        </a>
                    </div>

                    <!-- Developer Debug Hint -->
                    <div class="bg-orange-500/5 border border-orange-500/10 p-4 rounded-xl text-xs text-neutral-400 space-y-1">
                        <p class="font-semibold text-orange-400">⚡ Developer Debug Bypass Loaded:</p>
                        <p>You can use <code class="font-mono bg-black/40 px-1.5 py-0.5 rounded text-orange-300">lic-demo-pro</code> or <code class="font-mono bg-black/40 px-1.5 py-0.5 rounded text-orange-300">lic-demo-key</code> as a pre-approved key for instant live activation checks!</p>
                    </div>
                </div>

            <?php else: ?>
                <!-- ACTIVE/LICENSE OK SCREEN -->
                <div class="space-y-6">
                    <div class="text-center space-y-2">
                        <div class="inline-flex w-16 h-16 bg-emerald-500/10 text-emerald-500 rounded-full items-center justify-center border border-emerald-500/20 mb-2">
                            <!-- Success Checkmark -->
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-white tracking-tight">Active & Protected</h2>
                        <p class="text-sm text-neutral-400">
                            <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Application'); ?> is running live with a valid license
                        </p>
                    </div>

                    <?php if (!empty($success_message)): ?>
                        <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-xl flex items-start space-x-3 text-sm text-emerald-400">
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="bg-neutral-950 p-5 rounded-xl border border-neutral-800 space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-neutral-500">License Key:</span>
                            <span class="font-mono text-emerald-400 font-semibold bg-emerald-500/5 border border-emerald-500/10 px-2 py-0.5 rounded">
                                <?php echo htmlspecialchars(substr($storedKey, 0, 4) . '••••••••' . substr($storedKey, -4)); ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center text-sm border-t border-neutral-900 pt-3">
                            <span class="text-neutral-500">Plan Tier:</span>
                            <span class="uppercase tracking-wider text-xs font-bold text-white">
                                <?php echo htmlspecialchars($license_data['plan'] ?? 'Pro'); ?>
                            </span>
                        </div>

                        <div class="flex justify-between items-center text-sm border-t border-neutral-900 pt-3">
                            <span class="text-neutral-500">API Requests Used:</span>
                            <span class="font-mono text-neutral-300">
                                <?php echo htmlspecialchars($license_data['requestsUsed'] ?? 0); ?>
                            </span>
                        </div>

                        <div class="flex justify-between items-center text-sm border-t border-neutral-900 pt-3">
                            <span class="text-neutral-500">Expiry Date:</span>
                            <span class="font-mono text-neutral-300">
                                <?php 
                                    if (isset($license_data['expiresAt'])) {
                                        echo date('F d, Y', intval($license_data['expiresAt'] / 1000));
                                    } else {
                                        echo "Never (Lifetime)";
                                    }
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- Core panel files can safely run directly below this check -->
                    <div class="bg-orange-500/5 border border-orange-500/10 p-5 rounded-xl text-center">
                        <p class="text-sm font-semibold text-white mb-1">🎉 Key Verification Complete</p>
                        <p class="text-xs text-neutral-400 leading-relaxed">
                            Your license is fully active and verified. You are authorized to access the software resources on this host domain.
                        </p>
                    </div>

                    <a 
                        href="index.php?action=deactivate"
                        class="w-full text-center block py-3.5 bg-red-500/10 hover:bg-red-500/20 active:bg-red-500/30 border border-red-500/20 text-red-400 font-medium text-sm rounded-xl transition-all cursor-pointer"
                    >
                        Deactivate / Revoke Key
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-6 text-xs text-neutral-600 border-t border-neutral-900/60 bg-neutral-950/20">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Platform'); ?>. Licensed & Secured Protection</p>
    </footer>

</body>
</html>
