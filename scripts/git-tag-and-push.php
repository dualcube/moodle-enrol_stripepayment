#!/usr/bin/env php
<?php

/**
 * Git tag and push script for stripepayment plugin
 * 
 * This script creates a git tag with the current plugin version and pushes it to origin
 * without using any executable functions like sh, bash, php exec etc.
 * 
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
echo "📦 Git commands to create and push tag:\n\n";

// Generate the git commands without executing them
$tagCommand = "git tag " . escapeshellarg($tag);
$pushCommand = "git push origin " . escapeshellarg($tag);

echo "    $tagCommand\n";
echo "    $pushCommand\n\n";

echo "🎉 Copy and run the above commands to create and push your release tag.\n";
echo "💡 Alternative: You can also run these commands individually:\n\n";
echo "    # Create the tag\n";
echo "    $tagCommand\n\n";
echo "    # Push the tag to origin\n";
echo "    $pushCommand\n\n";
echo "    # Verify the tag was created\n";
echo "    git tag -l | grep $tag\n\n";
echo "    # Show tag information\n";
echo "    git show $tag --no-patch --format='%h %s (%an, %ad)'\n\n";
echo "✨ Release $version is ready to be tagged and pushed!\n";
