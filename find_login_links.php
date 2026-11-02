<?php
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/frontend'));
foreach ($dir as $f) {
    if ($f->isFile() && in_array($f->getExtension(), ['html', 'js'])) {
        $content = file_get_contents($f->getPathname());
        if (preg_match_all('/(?:href|src|action|navigate|redirect|location)[^"\']*["\']([^"\']*login\.html[^"\']*)["\']/i', $content, $m)) {
            echo basename($f->getPathname()) . " in " . $f->getPath() . ":\n";
            foreach (array_unique($m[1]) as $link) {
                echo "   -> " . $link . "\n";
            }
        }
    }
}

