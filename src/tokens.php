<?php
$tokens = token_get_all('<?php  $i=0; $j = $i + 5; echo $j; ?>');

foreach ($tokens as $token) {
    if (is_array($token)) {
        echo "Line {$token[2]}: ", token_name($token[0]), " ('{$token[1]}')", PHP_EOL;
    }
}