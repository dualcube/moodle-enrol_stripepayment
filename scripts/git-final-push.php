#!/usr/bin/env php
<?php

$versionFile = __DIR__ . '/../version.php';

if (!file_exists($versionFile)) {
    echo "❌ version.php not found.\n";
    exit(1);
}

$content = file_get_contents($versionFile);
if (!preg_match("/\\\$plugin->release\\s*=\\s*'([^']+)'/", $content, $matches)) {
    echo "❌ Could not extract release version from version.php\n";
    exit(1);
}

$release = $matches[1];  // e.g., 3.5.1.1 (Build: 2025071800)
$version = preg_replace('/\s*\(.*\)$/', '', $release);  // -> 3.5.1.1
$tag = 'v' . $version;

echo "✅ Extracted version: $version\n";
echo "📦 Suggested Git commands:\n\n";
echo "    git tag $tag\n";
echo "    git push origin $tag\n\n";
echo "🎉 Now run the above commands to push your release tag.\n";
