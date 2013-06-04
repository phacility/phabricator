JsShrink - Remove spaces and comments from JavaScript code
Available in PHP and JavaScript
Requires statements ending by semicolon, use JSHint or JSLint to verify.

http://vrana.github.com/JsShrink/

Usage PHP:
<?php
include "jsShrink.php";
echo jsShrink($code);
?>

Usage JavaScript:
<script type="text/javascript" src="jsShrink.js"></script>
<script type="text/javascript">
textarea.value = jsShrink(code);
</script>

Note:
Google Closure Compiler is much more powerful and efficient tool.
JsShrink was created for those looking for PHP or JavaScript only solution.
Most other JS minifiers are not able to process valid JavaScript code:
http://php.vrana.cz/minifikace-javascriptu.php#srovnani
