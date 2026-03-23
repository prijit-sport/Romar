<?php
$lines = file('modules/dashboard.php');
foreach (range(430,520) as $i) {
    echo $i . ': ' . rtrim($lines[$i-1]) . "\n";
}
