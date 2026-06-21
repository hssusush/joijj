import { useState, useEffect, useMemo } from 'react';
import { useSearchParams } from 'react-router';
import { auth, db } from '../firebase';
import { doc, onSnapshot, updateDoc, setDoc, query, collection, where } from 'firebase/firestore';
import { CreditCard, Loader2, TrendingUp, CheckCircle2, KeyRound, Copy, Check, Terminal, Code } from 'lucide-react';
import { LineChart, Line, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function Dashboard() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [userProfile, setUserProfile] = useState<any>(null);
  const [keys, setKeys] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [checkingOut, setCheckingOut] = useState(false);
  const [copied, setCopied] = useState(false);
  const [copiedComplete, setCopiedComplete] = useState(false);
  const [activeTab, setActiveTab] = useState<'quick' | 'complete'>('quick');

  useEffect(() => {
    const uid = auth.currentUser?.uid;
    if (!uid) return;

    const sessionId = searchParams.get('session_id');
    if (sessionId) {
      verifyCheckout(sessionId, uid);
    }

    const unsubProfile = onSnapshot(doc(db, 'users', uid), (docSnap) => {
      if (docSnap.exists()) {
        setUserProfile(docSnap.data());
      } else {
        setDoc(doc(db, 'users', uid), {
          email: auth.currentUser?.email || "",
          planId: 'starter',
          status: 'active',
          createdAt: Date.now(),
          updatedAt: Date.now()
        });
      }
      setLoading(false);
    });

    const qKeys = query(collection(db, 'license_keys'), where('userId', '==', uid));
    const unsubKeys = onSnapshot(qKeys, (snapshot) => {
      setKeys(snapshot.docs.map(d => d.data()));
    });

    return () => {
      unsubProfile();
      unsubKeys();
    };
  }, [searchParams]);

  const activationData = useMemo(() => {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const currentMonth = new Date().getMonth();
    const currentYear = new Date().getFullYear();

    const grouped: Record<string, number> = {};
    keys.forEach(k => {
      if (!k.createdAt) return;
      const d = new Date(k.createdAt);
      const label = `${months[d.getMonth()]} ${d.getFullYear().toString().slice(2)}`;
      grouped[label] = (grouped[label] || 0) + 1;
    });

    const result = [];
    for (let i = 5; i >= 0; i--) {
      const d = new Date(currentYear, currentMonth - i, 1);
      const label = `${months[d.getMonth()]} ${d.getFullYear().toString().slice(2)}`;
      result.push({
        name: label,
        activations: grouped[label] || 0
      });
    }
    return result;
  }, [keys]);

  const verifyCheckout = async (sessionId: string, uid: string) => {
    try {
      const res = await fetch(`/api/verify-session?session_id=${sessionId}`);
      const data = await res.json();
      if (data.success && data.planId) {
        await updateDoc(doc(db, 'users', uid), { 
           planId: data.planId, 
           updatedAt: Date.now() 
        });
        setSearchParams({}, { replace: true });
      }
    } catch (e) {
      console.error(e);
    }
  };

  const startCheckout = async (planId: string) => {
    setCheckingOut(true);
    try {
      const res = await fetch('/api/create-checkout-session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
           planId, 
           email: auth.currentUser?.email,
           userId: auth.currentUser?.uid
        })
      });
      const data = await res.json();
      if (data.url) {
        window.location.href = data.url;
      }
    } catch (e) {
      console.error(e);
    }
    setCheckingOut(false);
  };

  if (loading) return <div className="min-h-screen bg-transparent flex items-center justify-center"><Loader2 className="w-6 h-6 text-orange-500 animate-spin" /></div>;

  return (
    <div className="w-full">
      <main className="max-w-6xl mx-auto px-6 py-12">
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-12 space-y-4 md:space-y-0">
          <div>
            <h1 className="text-3xl font-bold text-black dark:text-white tracking-tight mb-2">Dashboard Overview</h1>
            <p className="text-neutral-600 dark:text-neutral-400">View your active subscription and usage analytics.</p>
          </div>
          <div className="flex items-center space-x-3">
            <div className="px-3 py-1.5 rounded-full bg-gray-100 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-xs font-mono text-neutral-800 dark:text-neutral-300 flex items-center space-x-2">
               <span className="w-2 h-2 rounded-full bg-green-500 opacity-80" />
               <span>SYSTEM ONLINE</span>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-1 space-y-6">
            <div className="bg-gray-50 dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl p-6 relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-32 h-32 bg-orange-500/5 blur-3xl rounded-full group-hover:bg-orange-500/10 transition-all" />
              
              <div className="flex items-center justify-between mb-6">
                <h3 className="text-lg font-medium text-black dark:text-white flex items-center space-x-2">
                  <CreditCard className="w-5 h-5 text-neutral-500 dark:text-neutral-400" />
                  <span>Subscription</span>
                </h3>
                <span className="px-2.5 py-1 bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-md text-xs font-mono uppercase text-orange-500 dark:text-orange-400">
                  {userProfile?.planId || 'Starter'}
                </span>
              </div>
              
              <div className="space-y-4 mb-8">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-neutral-500">Status</span>
                  <span className="text-black dark:text-white flex items-center space-x-1">
                     <CheckCircle2 className="w-4 h-4 text-green-500" />
                     <span className="capitalize">{userProfile?.status || 'Active'}</span>
                  </span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-neutral-500">API Limits</span>
                  <span className="text-neutral-800 dark:text-neutral-300">{userProfile?.planId === 'pro' ? 'Unlimited' : '10k req/mo'}</span>
                </div>
              </div>

              {userProfile?.planId !== 'pro' ? (
                <button 
                  onClick={() => startCheckout('pro')}
                  disabled={checkingOut}
                  className="w-full bg-black dark:bg-white text-white dark:text-black py-2.5 rounded-lg text-sm font-medium hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors disabled:opacity-50 flex justify-center"
                >
                  {checkingOut ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Upgrade to Pro'}
                </button>
              ) : (
                <div className="w-full bg-orange-500/10 border border-orange-500/20 text-orange-600 dark:text-orange-400 py-2.5 rounded-lg text-sm font-medium flex justify-center">
                  Pro Plan Active
                </div>
              )}
            </div>
          </div>

          <div className="lg:col-span-2">
            <div className="bg-gray-50 dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl p-6">
              <div className="flex items-center justify-between mb-8">
                <div>
                  <h3 className="text-lg font-medium text-black dark:text-white flex items-center space-x-2">
                    <KeyRound className="w-5 h-5 text-orange-500" />
                    <span>License Activations</span>
                  </h3>
                  <p className="text-sm text-neutral-500 mt-1">Track key generation and activation trends over time</p>
                </div>
                <div className="flex space-x-4">
                   <div className="text-right">
                      <div className="text-xs text-neutral-500">Total Keys</div>
                      <div className="text-lg font-mono text-black dark:text-white">{keys.length}</div>
                   </div>
                   <div className="text-right border-l border-neutral-200 dark:border-neutral-800 pl-4">
                      <div className="text-xs text-neutral-500">Active Keys</div>
                      <div className="text-lg font-mono text-black dark:text-white">{keys.filter(k => k.status === 'active').length}</div>
                   </div>
                </div>
              </div>
              
              <div className="h-64 w-full">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={activationData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                    <defs>
                      <linearGradient id="colorActivations" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#f97316" stopOpacity={0.3}/>
                        <stop offset="95%" stopColor="#f97316" stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="#262626" vertical={false} />
                    <XAxis dataKey="name" stroke="#525252" fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis stroke="#525252" fontSize={12} tickLine={false} axisLine={false} allowDecimals={false} />
                    <Tooltip 
                      contentStyle={{ backgroundColor: '#0a0a0a', borderColor: '#262626', borderRadius: '8px' }}
                      itemStyle={{ color: '#fff' }}
                      formatter={(value: any) => [`${value} Keys`, 'Activations']}
                    />
                    <Area type="monotone" dataKey="activations" stroke="#f97316" strokeWidth={2} fillOpacity={1} fill="url(#colorActivations)" />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </div>
          </div>
        </div>

        {/* SMM Panel / Web App Integration Card */}
        <div className="mt-12 bg-white dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl overflow-hidden shadow-sm">
          <div className="border-b border-neutral-200 dark:border-neutral-800 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center">
                <Terminal className="w-5 h-5 text-orange-500" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-black dark:text-white">Domain-Agnostic Integration Guide</h3>
                <p className="text-xs text-neutral-500 font-medium">Fully Hosted Licensing & Validation — No Paid Hosting Required!</p>
              </div>
            </div>
            <span className="self-start md:self-auto px-3 py-1 bg-green-500/10 text-green-600 dark:text-green-400 border border-green-500/20 rounded-full text-xs font-semibold uppercase tracking-wider font-mono">
              Live & Hosted Free
            </span>
          </div>

          {/* Navigation Tabs */}
          <div className="flex border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/40">
            <button 
              onClick={() => setActiveTab('quick')}
              className={`flex-1 md:flex-none px-6 py-3.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all cursor-pointer ${activeTab === 'quick' ? 'border-orange-500 text-orange-500 bg-orange-500/[0.02]' : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-white'}`}
            >
              ⚡ Option A: Fast Embed Code
            </button>
            <button 
              onClick={() => setActiveTab('complete')}
              className={`flex-1 md:flex-none px-6 py-3.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all cursor-pointer ${activeTab === 'complete' ? 'border-orange-500 text-orange-500 bg-orange-500/[0.02]' : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-white'}`}
            >
              📁 Option B: Full index.php Activation Page
            </button>
          </div>

          <div className="p-6 space-y-6">
            <div className="bg-orange-500/5 border border-orange-500/10 p-5 rounded-xl space-y-2">
              <h4 className="text-sm font-semibold text-orange-600 dark:text-orange-400 flex items-center space-x-2">
                <span>⚡ How is this Hosted Free?</span>
              </h4>
              <p className="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">
                Aapke is licensing key dashboard controller ko run karne ke liye kissi paid PHP hosting ya VPS server ki zarurat nahi hai!
                Google AI Studio has deployed your serverless database backend directly inside high-speed Cloud containers. 
                Use these files to lock or protect SMM panels and check license validity instantly.
              </p>
            </div>

            {activeTab === 'quick' ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Code className="w-4 h-4 text-neutral-400" />
                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">PHP Fast Integration Code (For SMM Core)</span>
                  </div>
                  <button
                    onClick={() => {
                      navigator.clipboard.writeText(`<?php
// Function to validate license key in your PHP source code
function checkLicense($licenseKey) {
    // Hosted fully free on Cloud Run / AI Studio
    $host = "https://ais-pre-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app";
    $apiUrl = $host . "/api/validate-license?key=" . urlencode($licenseKey);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['valid' => false, 'message' => 'Connection Error: ' . curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Usage inside your application script:
$licenseKey = "USER-COPIED-LICENSE-KEY";
$verification = checkLicense($licenseKey);

if (!$verification || empty($verification['valid'])) {
    die("Error: License is invalid, expired, or deactivated!");
}
?>`);
                      setCopied(true);
                      setTimeout(() => setCopied(false), 2000);
                    }}
                    className="flex items-center space-x-1.5 px-3 py-1.5 text-xs font-medium border border-neutral-300 dark:border-neutral-700 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition-colors cursor-pointer"
                  >
                    {copied ? (
                      <>
                        <Check className="w-3.5 h-3.5 text-green-500" />
                        <span>Copied!</span>
                      </>
                    ) : (
                      <>
                        <Copy className="w-3.5 h-3.5" />
                        <span>Copy Embed PHP</span>
                      </>
                    )}
                  </button>
                </div>

                <div className="bg-black/95 dark:bg-black rounded-xl p-5 border border-neutral-200 dark:border-neutral-800 font-mono text-xs overflow-x-auto text-emerald-400">
                  <pre>{`<?php
// Function to check key validity in your PHP script
function checkLicense($licenseKey) {
    $host = "https://ais-pre-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app";
    $apiUrl = $host . "/api/validate-license?key=" . urlencode($licenseKey);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['valid' => false, 'message' => curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Run protection check
$licenseKey = "USER-COPIED-LICENSE-KEY";
$verification = checkLicense($licenseKey);

if (!$verification || empty($verification['valid'])) {
    die("Error: License is invalid, expired, or deactivated!");
}
?>`}</pre>
                </div>
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Code className="w-4 h-4 text-neutral-400" />
                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">Complete index.php Script (With beautiful Tailwind UI Form)</span>
                  </div>
                  <button
                    onClick={() => {
                      navigator.clipboard.writeText(`<?php
session_start();

define('API_HOST', 'https://ais-pre-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app');
define('LICENSE_MANAGER_URL', 'https://ais-dev-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app');

function validateLicenseKey($key) {
    if (empty($key)) return ['valid' => false, 'message' => 'Please enter a license key.'];
    $apiUrl = API_HOST . "/api/validate-license?key=" . urlencode($key);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    if (curl_errno($ch)) return ['valid' => false, 'message' => 'Connection Error: ' . curl_error($ch)];
    curl_close($ch);
    $data = json_decode($response, true);
    return $data ? $data : ['valid' => false, 'message' => 'Invalid server response.'];
}

$error_message = '';
$success_message = '';

if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
    unset($_SESSION['bestsmm_license_key']);
    unset($_SESSION['bestsmm_license_data']);
    session_destroy();
    header("Location: index.php");
    exit();
}

if (isset($_POST['activate_license'])) {
    $enteredKey = trim($_POST['license_key'] ?? '');
    if (empty($enteredKey)) {
        $error_message = 'License key field cannot be empty!';
    } else {
        $res = validateLicenseKey($enteredKey);
        if (!empty($res['valid'])) {
            $_SESSION['bestsmm_license_key'] = $enteredKey;
            $_SESSION['bestsmm_license_data'] = $res;
            $success_message = "License successfully validated!";
        } else {
            $error_message = isset($res['message']) ? $res['message'] : 'The entered license key is invalid or expired.';
        }
    }
}

$is_valid = false;
$license_data = null;
if (isset($_SESSION['bestsmm_license_key'])) {
    $storedKey = $_SESSION['bestsmm_license_key'];
    $res = validateLicenseKey($storedKey);
    if (!empty($res['valid'])) {
        $is_valid = true;
        $license_data = $res;
    } else {
        unset($_SESSION['bestsmm_license_key']);
        unset($_SESSION['bestsmm_license_data']);
        $error_message = "Your active license session expired remotely.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Activation'); ?> Activation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #070708; }</style>
</head>
<body class="text-neutral-200 min-h-screen flex flex-col justify-between">
    <header class="border-b border-neutral-900 bg-neutral-950/80 p-4">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <span class="text-white font-bold">License Key Protection</span>
            <span class="text-xs bg-orange-500/10 text-orange-400 px-2 py-0.5 rounded border border-orange-500/20"><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'yourdomain.com'); ?></span>
        </div>
    </header>
    <main class="max-w-md w-full mx-auto p-4 flex-grow flex items-center">
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 w-full shadow-2xl">
            <?php if (!$is_valid): ?>
                <div class="space-y-4">
                    <div class="text-center">
                        <h2 class="text-xl font-bold text-white">License Check Required</h2>
                        <p class="text-xs text-neutral-400">Please enter your active key key code to continue</p>
                    </div>
                    <?php if ($error_message): ?>
                        <div class="p-3 bg-red-500/10 border border-red-500/20 text-red-400 text-xs rounded"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="index.php" class="space-y-3">
                        <input type="text" name="license_key" placeholder="XXXX-XXXX-XXXX-XXXX" class="w-full p-3 bg-black border border-neutral-800 rounded text-center font-mono">
                        <button type="submit" name="activate_license" class="w-full p-3 bg-orange-500 text-white font-bold rounded hover:bg-orange-600 transition-colors">Activate</button>
                    </form>
                    <div class="text-center pt-3 border-t border-neutral-800/50">
                        <a href="<?php echo LICENSE_MANAGER_URL; ?>" target="_blank" class="text-xs text-neutral-400 hover:text-white underline">Get License from Dashboard &rarr;</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="space-y-4 text-center">
                    <div class="inline-flex p-3 bg-emerald-500/10 text-emerald-500 rounded-full border border-emerald-500/20">✔️</div>
                    <h2 class="text-lg font-bold text-white">Active & Validated</h2>
                    <p class="text-xs text-neutral-400">Your licensing system is running fully live and authenticated!</p>
                    <div class="text-left bg-black p-3 rounded font-mono text-xs space-y-1">
                        <div>Key: <?php echo htmlspecialchars($storedKey); ?></div>
                        <div>Plan: <?php echo htmlspecialchars($license_data['plan'] ?? 'Pro'); ?></div>
                        <div>Requests: <?php echo htmlspecialchars($license_data['requestsUsed'] ?? 0); ?></div>
                    </div>
                    <a href="index.php?action=deactivate" class="block p-3 bg-red-500/10 text-red-400 text-xs rounded hover:bg-red-500/20 text-center">Deactivate Key</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>`);
                      setCopiedComplete(true);
                      setTimeout(() => setCopiedComplete(false), 2000);
                    }}
                    className="flex items-center space-x-1.5 px-3 py-1.5 text-xs font-medium border border-neutral-300 dark:border-neutral-700 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition-colors cursor-pointer"
                  >
                    {copiedComplete ? (
                      <>
                        <Check className="w-3.5 h-3.5 text-green-500" />
                        <span>Copied!</span>
                      </>
                    ) : (
                      <>
                        <Copy className="w-3.5 h-3.5" />
                        <span>Copy Code</span>
                      </>
                    )}
                  </button>
                </div>

                <div className="bg-black/95 dark:bg-black rounded-xl p-5 border border-neutral-200 dark:border-neutral-800 font-mono text-xs overflow-y-auto text-emerald-400">
                  <div className="mb-2 text-neutral-500 border-b border-neutral-800 pb-2 flex items-center justify-between text-[10px] uppercase font-bold tracking-wider">
                    <span>index.php with Tailwind UI Form</span>
                    <span className="text-orange-500/80 bg-orange-500/10 px-1.5 py-0.5 rounded font-mono">Bypasses Paid Hosting</span>
                  </div>
                  <pre className="max-h-[350px] overflow-y-auto">{`<?php
session_start();

define('API_HOST', 'https://ais-pre-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app');
define('LICENSE_MANAGER_URL', 'https://ais-dev-pc62xdvmfpzrqzvndi2rpl-280169348141.asia-southeast1.run.app');

function validateLicenseKey($key) {
    if (empty($key)) return ['valid' => false, 'message' => 'Please enter a key.'];
    $apiUrl = API_HOST . "/api/validate-license?key=" . urlencode($key);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    if (curl_errno($ch)) return ['valid' => false, 'message' => curl_error($ch)];
    curl_close($ch);
    $data = json_decode($response, true);
    return $data ? $data : ['valid' => false, 'message' => 'Server Error'];
}

$error_message = '';
$success_message = '';

if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
    unset($_SESSION['bestsmm_license_key']);
    unset($_SESSION['bestsmm_license_data']);
    session_destroy();
    header("Location: index.php");
    exit();
}

if (isset($_POST['activate_license'])) {
    $enteredKey = trim($_POST['license_key'] ?? '');
    if (empty($enteredKey)) {
        $error_message = 'License key field cannot be empty!';
    } else {
        $res = validateLicenseKey($enteredKey);
        if (!empty($res['valid'])) {
            $_SESSION['bestsmm_license_key'] = $enteredKey;
            $_SESSION['bestsmm_license_data'] = $res;
            $success_message = "License successfully validated!";
        } else {
            $error_message = isset($res['message']) ? $res['message'] : 'Key is invalid.';
        }
    }
}

$is_valid = false;
$license_data = null;
if (isset($_SESSION['bestsmm_license_key'])) {
    $storedKey = $_SESSION['bestsmm_license_key'];
    $res = validateLicenseKey($storedKey);
    if (!empty($res['valid'])) {
        $is_valid = true;
        $license_data = $res;
    } else {
        unset($_SESSION['bestsmm_license_key']);
        unset($_SESSION['bestsmm_license_data']);
        $error_message = "Your active license session expired remotely.";
    }
}
?>
<!-- Web Interface continues here... -->`}</pre>
                </div>
              </div>
            )}
          </div>
        </div>
      </main>
    </div>
  );
}
