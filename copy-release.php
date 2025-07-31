<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External library for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
