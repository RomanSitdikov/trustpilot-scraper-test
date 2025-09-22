#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

use App\TrustpilotScraper;
use App\Database;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$imagesDir = $_ENV['IMAGES_DIR'] ?? 'images';

$db = new Database([
    'driver' => $_ENV['DB_DRIVER'],
    'host' => $_ENV['DB_HOST'],
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD']
]);

$scraper = new TrustpilotScraper($db, $imagesDir);

$options = getopt('', ['init-db', 'source::']);

if (isset($options['init-db'])) {
    echo "Initializing DB...\n";
    $sql = file_get_contents(__DIR__ . '/migrations/init.sql');
    $db->exec($sql);
    echo "DB initialized.\n";
    exit;
}

$urls = [];
if (!empty($options['source'])) {
    $file = $options['source'];
    if (!file_exists($file)) {
        echo "Source file not found: $file\n";
        exit;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $urls[] = trim($line);
    }
}

if (empty($urls)) {
    echo "No URLs provided. Use --source=links.txt or --url=...\n";
    exit;
}

foreach ($urls as $u) {
    echo "Processing: $u\n";
    try {
        $scraper->scrapeListingPage($u);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
