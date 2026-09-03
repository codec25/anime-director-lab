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

$stages = (string) file_get_contents($root . '/assets/js/production-stages.js');
$assert(str_contains($stages, 'directorToolMenuHost') && str_contains($stages, 'directorToolContent'), 'Unified Director sheet must keep separate persistent menu and tool-content hosts.');
$assert(!str_contains($stages, "body.innerHTML=`<div class=\"director-tools-menu\""), 'Director tools menu must never overwrite the adopted tool container.');

$readiness = (string) file_get_contents($root . '/assets/js/production-readiness.js');
$assert(!str_contains($readiness, "$('#readinessLabel').textContent"), 'Readiness fallback references a removed DOM label.');

foreach (['KlingProvider.php','GoogleProvider.php','WanProvider.php'] as $retiredProvider) {
    $assert(!is_file($root . '/app/providers/' . $retiredProvider), "Retired provider stub still exists: {$retiredProvider}");
}
$bootstrap = (string) file_get_contents($root . '/app/bootstrap.php');
$gateway = (string) file_get_contents($root . '/app/gateway.php');
foreach (['KlingProvider','GoogleProvider','WanProvider'] as $retiredClass) {
    $assert(!str_contains($bootstrap, $retiredClass), "Bootstrap still loads retired provider: {$retiredClass}");
    $assert(!str_contains($gateway, $retiredClass), "Gateway still exposes retired provider: {$retiredClass}");
}

$gatePatterns = [
    'premium_only','pro_only','subscription_required','membership_required','upgrade_required',
    'requires_subscription','requires_membership','locked_by_plan','paywall'
];
$scanRoots = [$root . '/app', $root . '/assets/js'];
$scanFiles = [];
foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php','js'], true)) $scanFiles[] = $file->getPathname();
}
foreach (glob($root . '/*.php') ?: [] as $file) $scanFiles[] = $file;
foreach (array_unique($scanFiles) as $file) {
    $source = strtolower((string) file_get_contents($file));
    foreach ($gatePatterns as $pattern) $assert(!str_contains($source, $pattern), 'Test build contains a membership/paywall gate in ' . str_replace($root . '/', '', $file) . ': ' . $pattern);
}

$sw = (string) file_get_contents($root . '/sw.js');
$assert(str_contains($sw, "anime-director-shell-v2"), 'Service worker cache version was not advanced.');
$assert(strpos($sw, 'fetch(req)') < strpos($sw, 'caches.match(req)'), 'Static assets must prefer the network before cached fallback.');

if ($failures) {
    fwrite(STDERR, "Director surface audit failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Director surface audit passed: assets are current, tools persist, retired stubs are gone, and test access is ungated.\n";
