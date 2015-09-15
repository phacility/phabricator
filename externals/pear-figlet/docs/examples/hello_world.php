<?php

/* UTF-8 convert to FIGlet Unicode */
/* iconv PHP module required */
function utf8tofiglet($str)
{
    // escape %u
    $str = str_replace('%u', sprintf('%%%%u%04X', ord('u')), $str);

    if (function_exists('iconv')) {
        $str = iconv('utf-8', 'ucs-2be', $str);
        $out = '';

        for ($i = 0, $len = strlen($str); $i<$len; $i++) {
            $code = ord($str[$i++]) * 256 + ord($str[$i]);

            $out .= $code < 128 ? $str[$i] : sprintf('%%u%04X', $code);
        }

        return $out;
    }

    return $str;
}

require_once 'Text/Figlet.php';

$figlet = new Text_Figlet();
$error  = $figlet->LoadFont('makisupa.flf');
if (PEAR::isError($error)) {
    echo 'Error: ' . $error->getMessage() . "\n";
} else {
    echo $figlet->LineEcho(utf8tofiglet('Hello, world!')) . "\n";
}
?>