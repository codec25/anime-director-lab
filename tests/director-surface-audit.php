<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$assets = static function (string $html): array {
    preg_match_all('/(?:src|href)="((?:assets\/)[^"?]+)(?:\?[^" ]*)?"/', $html, $matches);
    return $matches[1] ?? [];
};

$director = (string) file_get_contents($root . '/director.php');
$lab = (string) file_get_contents($root . '/lab.php');
$directorAssets = $assets($director);
$labAssets = $assets($lab);

$assert(count($directorAssets) === count(array_unique($directorAssets)), 'Director loads a duplicate CSS or JavaScript asset.');
$assert(count($labAssets) === count(array_unique($labAssets)), 'Advanced loads a duplicate CSS or JavaScript asset.');
foreach (array_unique(array_merge($directorAssets, $labAssets)) as $asset) {
    $assert(is_file($root . '/' . $asset), "Referenced asset is missing: {$asset}");
}
$assert(!in_array('assets/js/app.js', $directorAssets, true), 'Advanced app.js must not run in Director.');
$assert(!in_array('assets/css/app.css', $directorAssets, true), 'Advanced app.css must not style Director.');
$assert(!in_array('assets/js/memory-disclosure.js', $directorAssets, true), 'Retired memory-disclosure.js is active.');
$assert(!in_array('assets/css/memory-disclosure.css', $directorAssets, true), 'Retired memory-disclosure.css is active.');

$observerOwners = [];
foreach ($directorAssets as $asset) {
    if (!str_ends_with($asset, '.js')) continue;
    $source = (string) file_get_contents($root . '/' . $asset);
    if (str_contains($source, 'MutationObserver')) $observerOwners[] = $asset;
}
$assert($observerOwners === ['assets/js/ui-observer.js'], 'Director must have one shared MutationObserver owner; found: ' . implode(', ', $observerOwners));

$sw = (string) file_get_contents($root . '/sw.js');
$assert(str_contains($sw, "anime-director-shell-v2"), 'Service worker cache version was not advanced.');
$assert(strpos($sw, 'fetch(req)') < strpos($sw, 'caches.match(req)'), 'Static assets must prefer the network before cached fallback.');

if ($failures) {
    fwrite(STDERR, "Director surface audit failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Director surface audit passed: active assets are unique, separated, present, and current.\n";
