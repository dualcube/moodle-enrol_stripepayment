<?php
function copyRecursive($source, $dest) {
    if (is_dir($source)) {
        @mkdir($dest, 0755, true);
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            copyRecursive("$source/$item", "$dest/$item");
        }
    } elseif (is_file($source)) {
        @copy($source, $dest);
    }
}

$exclude = ['release', '.', '..'];
$files = array_diff(scandir('.'), $exclude);

foreach ($files as $file) {
    if ($file[0] === '.') {
        continue;
    }
    copyRecursive($file, "release/stripepayment/$file");
}
