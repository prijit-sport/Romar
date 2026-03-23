<?php
$lines = file("modules/dashboard.php");
$line = $lines[451];
echo trim($line) . "\n";
echo "Converted: " . mb_convert_encoding($line, "UTF-8", "windows-874") . "\n";

