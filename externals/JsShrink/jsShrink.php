<?php
/** Remove spaces and comments from JavaScript code
* @param string code with commands terminated by semicolon
* @return string shrinked code
* @link http://vrana.github.com/JsShrink/
* @author Jakub Vrana, http://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
function jsShrink($input) {
	return preg_replace_callback('(
		(?:
			(^|[-+\([{}=,:;!%^&*|?~]|/(?![/*])|return|throw) # context before regexp
			(?:\s|//[^\n]*+\n|/\*(?:[^*]|\*(?!/))*+\*/)* # optional space
			(/(?![/*])(?:
				\\\\[^\n]
				|[^[\n/\\\\]++
				|\[(?:\\\\[^\n]|[^]])++
			)+/) # regexp
			|(^
				|\'(?:\\\\.|[^\n\'\\\\])*\'
				|"(?:\\\\.|[^\n"\\\\])*"
				|([0-9A-Za-z_$]+)
				|([-+]+)
				|.
			)
		)(?:\s|//[^\n]*+\n|/\*(?:[^*]|\*(?!/))*+\*/)* # optional space
	)sx', 'jsShrinkCallback', "$input\n");
}

function jsShrinkCallback($match) {
	static $last = '';
	$match += array_fill(1, 5, null); // avoid E_NOTICE
	list(, $context, $regexp, $result, $word, $operator) = $match;
	if ($word != '') {
		$result = ($last == 'word' ? "\n" : ($last == 'return' ? " " : "")) . $result;
		$last = ($word == 'return' || $word == 'throw' || $word == 'break' ? 'return' : 'word');
	} elseif ($operator) {
		$result = ($last == $operator[0] ? "\n" : "") . $result;
		$last = $operator[0];
	} else {
		if ($regexp) {
			$result = $context . ($context == '/' ? "\n" : "") . $regexp;
		}
		$last = '';
	}
	return $result;
}
