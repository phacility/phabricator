<?php
/**
 * Diff Match and Patch
 *
 * Copyright 2006 Google Inc.
 * http://code.google.com/p/google-diff-match-patch/
 *
 * php port by Tobias Buschor shwups.ch
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @fileoverview Computes the difference between two texts to create a patch.
 * Applies the patch onto another text, allowing for errors.
 * @author fraser@google.com (Neil Fraser)
 */

/**
 * Class containing the diff, match and patch methods.
 * @constructor
 */
class diff_match_patch {

	// Defaults.
	// Redefine these in your program to override the defaults.

	// Number of seconds to map a diff before giving up (0 for infinity).
	public $Diff_Timeout = 1.0;
	// Cost of an empty edit operation in terms of edit characters.
	public $Diff_EditCost = 4;
	// The size beyond which the double-ended diff activates.
	// Double-ending is twice as fast, but less accurate.
	public $Diff_DualThreshold = 32;
	// At what point is no match declared (0.0 = perfection, 1.0 = very loose).
	public $Match_Threshold = 0.5;
	// How far to search for a match (0 = exact location, 1000+ = broad match).
	// A match this many characters away from the expected location will add
	// 1.0 to the score (0.0 is a perfect match).
	public $Match_Distance = 1000;
	// When deleting a large block of text (over ~64 characters), how close does
	// the contents have to match the expected contents. (0.0 = perfection,
	// 1.0 = very loose).  Note that Match_Threshold controls how closely the
	// end points of a delete need to match.
	public $Patch_DeleteThreshold = 0.5;
	// Chunk size for context length.
	public $Patch_Margin = 4;

	/**
	 * Compute the number of bits in an int.
	 * The normal answer for JavaScript is 32.
	 * @return {number} Max bits

	function getMaxBits() {
		var maxbits = 0;
		var oldi = 1;
		var newi = 2;
		while (oldi != newi) {
			maxbits++;
			oldi = newi;
			newi = newi << 1;
		}
		return maxbits;
	}
	// How many bits in a number?
	this.Match_MaxBits = getMaxBits();
	*/
	//  DIFF FUNCTIONS

	/**
	 * Find the differences between two texts.  Simplifies the problem by stripping
	 * any common prefix or suffix off the texts before diffing.
	 * @param {string} text1 Old string to be diffed.
	 * @param {string} text2 New string to be diffed.
	 * @param {boolean} opt_checklines Optional speedup flag.  If present and false,
	 *     then don't run a line-level diff first to identify the changed areas.
	 *     Defaults to true, which does a faster, slightly less optimal diff
	 * @return {Array.<Array.<number|string>>} Array of diff tuples.
	 */
	function diff_main($text1, $text2, $checklines = true) {
		// Check for equality (speedup)
		if ($text1 === $text2) {
			return array ( array ( DIFF_EQUAL, $text1) );
		}

		// Trim off common prefix (speedup)
		$commonlength = $this->diff_commonPrefix($text1, $text2);
		$commonprefix = mb_substr($text1, 0, $commonlength);
		$text1 = mb_substr($text1, $commonlength);
		$text2 = mb_substr($text2, $commonlength);

		// Trim off common suffix (speedup)
		$commonlength = $this->diff_commonSuffix($text1, $text2);
		$commonsuffix = mb_substr($text1, mb_strlen($text1) - $commonlength);
		$text1 = mb_substr($text1, 0, mb_strlen($text1) - $commonlength);
		$text2 = mb_substr($text2, 0, mb_strlen($text2) - $commonlength);

		// Compute the diff on the middle block
		$diffs = $this->diff_compute($text1, $text2, $checklines);

		// Restore the prefix and suffix
		if ($commonprefix !== '') {
			array_unshift($diffs, array ( DIFF_EQUAL, $commonprefix ));
		}
		if ($commonsuffix !== '') {
			array_push($diffs, array ( DIFF_EQUAL, $commonsuffix ));
		}
		$this->diff_cleanupMerge($diffs);
		return $diffs;
	}

	/**
	 * Find the differences between two texts.  Assumes that the texts do not
	 * have any common prefix or suffix.
	 * @param {string} text1 Old string to be diffed.
	 * @param {string} text2 New string to be diffed.
	 * @param {boolean} checklines Speedup flag.  If false, then don't run a
	 *     line-level diff first to identify the changed areas.
	 *     If true, then run a faster, slightly less optimal diff
	 * @return {Array.<Array.<number|string>>} Array of diff tuples.
	 * @private
	 */
	function diff_compute($text1, $text2, $checklines) {

		if ($text1 === '') {
			// Just add some text (speedup)
			return array ( array ( DIFF_INSERT, $text2 ) );
		}

		if ($text2 === '') {
			// Just delete some text (speedup)
			return array ( array ( DIFF_DELETE, $text1 ) );
		}

		$longtext = mb_strlen($text1) > mb_strlen($text2) ? $text1 : $text2;
		$shorttext = mb_strlen($text1) > mb_strlen($text2) ? $text2 : $text1;
		$i = mb_strpos($longtext, $shorttext);
		if ($i !== false) {
			// Shorter text is inside the longer text (speedup)
			$diffs = array (
				array ( DIFF_INSERT, mb_substr($longtext, 0, $i) ),
				array ( DIFF_EQUAL, $shorttext ),
				array ( DIFF_INSERT, mb_substr($longtext, $i +mb_strlen($shorttext)) )
			);

			// Swap insertions for deletions if diff is reversed.
			if (mb_strlen($text1) > mb_strlen($text2)) {
				$diffs[0][0] = $diffs[2][0] = DIFF_DELETE;
			}
			return $diffs;
		}
		$longtext = $shorttext = null; // Garbage collect

		// Check to see if the problem can be split in two.
		$hm = $this->diff_halfMatch($text1, $text2);
		if ($hm) {
			// A half-match was found, sort out the return data.
			$text1_a = $hm[0];
			$text1_b = $hm[1];
			$text2_a = $hm[2];
			$text2_b = $hm[3];
			$mid_common = $hm[4];
			// Send both pairs off for separate processing.
			$diffs_a = $this->diff_main($text1_a, $text2_a, $checklines);
			$diffs_b = $this->diff_main($text1_b, $text2_b, $checklines);
			// Merge the results.
			return array_merge($diffs_a, array (
				array (
					DIFF_EQUAL,
					$mid_common
				)
			), $diffs_b);
		}

		// Perform a real diff.
		if ($checklines && (mb_strlen($text1) < 100 || mb_strlen($text2) < 100)) {
			// Too trivial for the overhead.
			$checklines = false;
		}
		$linearray = null;
		if ($checklines) {
			// Scan the text on a line-by-line basis first.
			$a = $this->diff_linesToChars($text1, $text2);
			$text1 = $a[0];
			$text2 = $a[1];
			$linearray = $a[2];
		}
		$diffs = $this->diff_map($text1, $text2);
		if (!$diffs) {
			// No acceptable result.
			$diffs = array (
				array (
					DIFF_DELETE,
					$text1
				),
				array (
					DIFF_INSERT,
					$text2
				)
			);
		}
		if ($checklines) {
			// Convert the diff back to original text.
			$this->diff_charsToLines($diffs, $linearray);
			// Eliminate freak matches (e.g. blank lines)
			$this->diff_cleanupSemantic($diffs);

			// Rediff any replacement blocks, this time character-by-character.
			// Add a dummy entry at the end.
			array_push($diffs, array (
				DIFF_EQUAL,
				''
			));
			$pointer = 0;
			$count_delete = 0;
			$count_insert = 0;
			$text_delete = '';
			$text_insert = '';
			while ($pointer < count($diffs)) {
				switch ($diffs[$pointer][0]) {
					case DIFF_INSERT :
						$count_insert++;
						$text_insert .= $diffs[$pointer][1];
						break;
					case DIFF_DELETE :
						$count_delete++;
						$text_delete .= $diffs[$pointer][1];
						break;
					case DIFF_EQUAL :
						// Upon reaching an equality, check for prior redundancies.
						if ($count_delete >= 1 && $count_insert >= 1) {
							// Delete the offending records and add the merged ones.
							$a = $this->diff_main($text_delete, $text_insert, false);
							array_splice($diffs, $pointer - $count_delete - $count_insert, $count_delete + $count_insert);

							$pointer = $pointer - $count_delete - $count_insert;
							for ($j = count($a) - 1; $j >= 0; $j--) {
								array_splice($diffs, $pointer, 0, array($a[$j]));
							}
							$pointer = $pointer +count($a);
						}
						$count_insert = 0;
						$count_delete = 0;
						$text_delete = '';
						$text_insert = '';
						break;
				}
				$pointer++;
			}
			array_pop($diffs); // Remove the dummy entry at the end.
		}
		return $diffs;
	}

	/**
	 * Split two texts into an array of strings.  Reduce the texts to a string of
	 * hashes where each Unicode character represents one line.
	 * @param {string} text1 First string.
	 * @param {string} text2 Second string.
	 * @return {Array.<string|Array.<string>>} Three element Array, containing the
	 *     encoded text1, the encoded text2 and the array of unique strings.  The
	 *     zeroth element of the array of unique strings is intentionally blank.
	 * @private
	 */
	function diff_linesToChars($text1, $text2) {
		$lineArray = array(); // e.g. lineArray[4] == 'Hello\n'
		$lineHash = array(); // e.g. lineHash['Hello\n'] == 4

		// '\x00' is a valid character, but various debuggers don't like it.
		// So we'll insert a junk entry to avoid generating a null character.
		$lineArray[0] = '';

		$chars1 = $this->diff_linesToCharsMunge($text1, $lineArray, $lineHash);
		$chars2 = $this->diff_linesToCharsMunge($text2, $lineArray, $lineHash);
		return array (
			$chars1,
			$chars2,
			$lineArray
		);
	}

	/**
	 * Split a text into an array of strings.  Reduce the texts to a string of
	 * hashes where each Unicode character represents one line.
	 * Modifies linearray and linehash through being a closure.
	 * @param {string} text String to encode
	 * @return {string} Encoded string
	 * @private
	 */
	function diff_linesToCharsMunge($text, &$lineArray, &$lineHash) {
		$chars = '';
		// Walk the text, pulling out a mb_substring for each line.
		// text.split('\n') would would temporarily double our memory footprint.
		// Modifying text would create many large strings to garbage collect.
		$lineStart = 0;
		$lineEnd = -1;
		// Keeping our own length variable is faster than looking it up.
		$lineArrayLength = count($lineArray);
		while ($lineEnd < mb_strlen($text) - 1) {
			$lineEnd = mb_strpos($text, "\n", $lineStart);
			if ($lineEnd === false) {
				$lineEnd = mb_strlen($text) - 1;
			}
			$line = mb_substr($text, $lineStart, $lineEnd +1 -$lineStart);
			$lineStart = $lineEnd +1;

			if ( isset($lineHash[$line]) ) {
				$chars .= mb_chr($lineHash[$line]);
			} else {
				$chars .= mb_chr($lineArrayLength);
				$lineHash[$line] = $lineArrayLength;
				$lineArray[$lineArrayLength++] = $line;
			}
		}
		return $chars;
	}
	/**
	 * Rehydrate the text in a diff from a string of line hashes to real lines of
	 * text.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @param {Array.<string>} lineArray Array of unique strings.
	 * @private
	 */
	function diff_charsToLines(&$diffs, $lineArray) {
		for ($x = 0; $x < count($diffs); $x++) {
			$chars = $diffs[$x][1];
			$text = array ();
			for ($y = 0; $y < mb_strlen($chars); $y++) {
				$text[$y] = $lineArray[charCodeAt($chars, $y)];
			}
			$diffs[$x][1] = implode('',$text);
		}
	}

	/**
	 * Explore the intersection points between the two texts.
	 * @param {string} text1 Old string to be diffed.
	 * @param {string} text2 New string to be diffed.
	 * @return {Array.<Array.<number|string>>?} Array of diff tuples or null if no
	 *     diff available.
	 * @private
	 */
	function diff_map($text1, $text2) {
		// Don't run for too long.
		$ms_end = microtime(true) + $this->Diff_Timeout;
		
		// Cache the text lengths to prevent multiple calls.
		$text1_length = mb_strlen($text1);
		$text2_length = mb_strlen($text2);
		$max_d = $text1_length + $text2_length -1;
		$doubleEnd = $this->Diff_DualThreshold * 2 < $max_d;
		$v_map1 = array();
		$v_map2 = array();
		$v1 = array();
		$v2 = array();
		$v1[1] = 0;
		$v2[1] = 0;
		$x = null;
		$y = null;
		$footstep = null; // Used to track overlapping paths.
		$footsteps = array();
		$done = false;
		// Safari 1.x doesn't have hasOwnProperty
		//?    $hasOwnProperty = !!(footsteps.hasOwnProperty);
		// If the total number of characters is odd, then the front path will collide
		// with the reverse path.
		$front = ($text1_length + $text2_length) % 2;
		for ($d = 0; $d < $max_d; $d++) {
			// Bail out if timeout reached.
			if ($this->Diff_Timeout > 0 && microtime(true) > $ms_end) {
				return null; // zzz
			}

			// Walk the front path one step.
			$v_map1[$d] = array ();
			for ($k = -$d; $k <= $d; $k += 2) {
				if ($k == -$d || $k != $d && $v1[$k -1] < $v1[$k +1]) {
					$x = $v1[$k +1];
				} else {
					$x = $v1[$k -1] + 1;
				}
				$y = $x - $k;
				if ($doubleEnd) {
					$footstep = $x . ',' . $y;
					if ($front && isset ($footsteps[$footstep])) {
						$done = true;
					}
					if (!$front) {
						$footsteps[$footstep] = $d;
					}
				}
				while (!$done && ($x < $text1_length) && ($y < $text2_length) && (mb_substr($text1, $x, 1) == mb_substr($text2, $y, 1)) ) {
					$x++;
					$y++;
					if ($doubleEnd) {
						$footstep = $x . ',' . $y;
						if ($front && isset ($footsteps[$footstep])) {
							$done = true;
						}
						if (!$front) {
							$footsteps[$footstep] = $d;
						}
					}
				}
				$v1[$k] = $x;
				$v_map1[$d][$x . ',' . $y] = true;
				if ($x == $text1_length && $y == $text2_length) {
					// Reached the end in single-path mode.
					return $this->diff_path1($v_map1, $text1, $text2);
				}
				elseif ($done) {
					// Front path ran over reverse path.

					$v_map2 = array_slice($v_map2, 0, $footsteps[$footstep] + 1);
					$a = $this->diff_path1($v_map1, mb_substr($text1, 0, $x), mb_substr($text2, 0, $y));

					return array_merge($a, $this->diff_path2($v_map2, mb_substr($text1, $x), mb_substr($text2, $y)));
				}
			}

			if ($doubleEnd) {
				// Walk the reverse path one step.
				$v_map2[$d] = array();
				for ($k = -$d; $k <= $d; $k += 2) {
					if ($k == -$d || $k != $d && $v2[$k -1] < $v2[$k +1]) {
						$x = $v2[$k +1];
					} else {
						$x = $v2[$k -1] + 1;
					}
					$y = $x - $k;
					$footstep = ($text1_length - $x) . ',' . ($text2_length - $y);
					if (!$front && isset ($footsteps[$footstep])) {
						$done = true;
					}
					if ($front) {
						$footsteps[$footstep] = $d;
					}
					while (!$done && $x < $text1_length && $y < $text2_length && mb_substr($text1, $text1_length - $x -1, 1) == mb_substr($text2, $text2_length - $y -1, 1) ) {
						$x++;
						$y++;
						$footstep = ($text1_length - $x) . ',' . ($text2_length - $y);
						if (!$front && isset ($footsteps[$footstep])) {
							$done = true;
						}
						if ($front) {
							$footsteps[$footstep] = $d;
						}
					}
					$v2[$k] = $x;
					$v_map2[$d][$x . ',' . $y] = true;
					if ($done) {
						// Reverse path ran over front path.
						$v_map1 = array_slice($v_map1, 0, $footsteps[$footstep] + 1);
						$a = $this->diff_path1($v_map1, mb_substr($text1, 0, $text1_length - $x), mb_substr($text2, 0, $text2_length - $y));
						return array_merge($a, $this->diff_path2($v_map2, mb_substr($text1, $text1_length - $x), mb_substr($text2, $text2_length - $y)));
					}
				}
			}
		}
		// Number of diffs equals number of characters, no commonality at all.
		return null;
	}

	/**
	 * Work from the middle back to the start to determine the path.
	 * @param {Array.<Object>} v_map Array of paths.ers
	 * @param {string} text1 Old string fragment to be diffed.
	 * @param {string} text2 New string fragment to be diffed.
	 * @return {Array.<Array.<number|string>>} Array of diff tuples.
	 * @private
	 */
	function diff_path1($v_map, $text1, $text2) {
		$path = array ();
		$x = mb_strlen($text1);
		$y = mb_strlen($text2);
		/** @type {number?} */
		$last_op = null;
		for ($d = count($v_map) - 2; $d >= 0; $d--) {
			while (1) {
				if (isset ($v_map[$d][($x -1) . ',' . $y])) {
					$x--;
					if ($last_op === DIFF_DELETE) {
						$path[0][1] = mb_substr($text1, $x, 1) . $path[0][1];
					} else {
						array_unshift($path, array (
							DIFF_DELETE,
							mb_substr($text1, $x, 1)
						));
					}
					$last_op = DIFF_DELETE;
					break;
				} elseif (isset ($v_map[$d][$x . ',' . ($y -1)])) {
					$y--;
					if ($last_op === DIFF_INSERT) {
						$path[0][1] = mb_substr($text2, $y, 1) . $path[0][1];
					} else {
						array_unshift($path, array (
							DIFF_INSERT,
							mb_substr($text2, $y, 1)
						));
					}
					$last_op = DIFF_INSERT;
					break;
				} else {
					$x--;
					$y--;
					//if (text1.charAt(x) != text2.charAt(y)) {
					//  throw new Error('No diagonal.  Can\'t happen. (diff_path1)');
					//}
					if ($last_op === DIFF_EQUAL) {
						$path[0][1] = mb_substr($text1, $x, 1) . $path[0][1];
					} else {
						array_unshift($path, array (
							DIFF_EQUAL,
							mb_substr($text1, $x, 1)
						));
					}
					$last_op = DIFF_EQUAL;
				}
			}
		}
		return $path;
	}

	/**
	 * Work from the middle back to the end to determine the path.
	 * @param {Array.<Object>} v_map Array of paths.
	 * @param {string} text1 Old string fragment to be diffed.
	 * @param {string} text2 New string fragment to be diffed.
	 * @return {Array.<Array.<number|string>>} Array of diff tuples.
	 * @private
	 */
	function diff_path2($v_map, $text1, $text2) {
		$path = array ();
		$pathLength = 0;
		$x = mb_strlen($text1);
		$y = mb_strlen($text2);
		/** @type {number?} */
		$last_op = null;
		for ($d = count($v_map) - 2; $d >= 0; $d--) {
			while (1) {
				if (isset ($v_map[$d][($x -1) . ',' . $y])) {
					$x--;
					if ($last_op === DIFF_DELETE) {
						$path[$pathLength -1][1] .= $text1[mb_strlen($text1) - $x -1];
					} else {
						$path[$pathLength++] = array (
							DIFF_DELETE,
							$text1[mb_strlen($text1) - $x -1]
						);
					}
					$last_op = DIFF_DELETE;
					break;
				}
				elseif (isset ($v_map[$d][$x . ',' . ($y -1)])) {
					$y--;
					if ($last_op === DIFF_INSERT) {
						$path[$pathLength -1][1] .= $text2[mb_strlen($text2) - $y -1];
					} else {
						$path[$pathLength++] = array (
							DIFF_INSERT,
							$text2[mb_strlen($text2) - $y -1]
						);
					}
					$last_op = DIFF_INSERT;
					break;
				} else {
					$x--;
					$y--;
					//if (text1.charAt(text1.length - x - 1) !=
					//    text2.charAt(text2.length - y - 1)) {
					//  throw new Error('No diagonal.  Can\'t happen. (diff_path2)');
					//}
					if ($last_op === DIFF_EQUAL) {
						$path[$pathLength -1][1] .= $text1[mb_strlen($text1) - $x -1];
					} else {
						$path[$pathLength++] = array (
							DIFF_EQUAL,
							$text1[mb_strlen($text1) - $x -1]
						);
					}
					$last_op = DIFF_EQUAL;
				}
			}
		}
		return $path;
	}

	/**
	 * Determine the common prefix of two strings
	 * @param {string} text1 First string.
	 * @param {string} text2 Second string.
	 * @return {number} The number of characters common to the start of each
	 *     string.
	 */
	function diff_commonPrefix($text1, $text2) {
		for ($i = 0; 1; $i++) {
			$t1 = mb_substr($text1, $i, 1);
			$t2 = mb_substr($text2, $i, 1);
			if($t1==='' || $t2==='' || $t1 !== $t2 ){
				return $i;
			}
		}
	}

	/**
	 * Determine the common suffix of two strings
	 * @param {string} text1 First string.
	 * @param {string} text2 Second string.
	 * @return {number} The number of characters common to the end of each string.
	 */
	function diff_commonSuffix($text1, $text2) {
		return $this->diff_commonPrefix( strrev($text1), strrev($text2) );
	}

	/**
	 * Do the two texts share a mb_substring which is at least half the length of the
	 * longer text?
	 * @param {string} text1 First string.
	 * @param {string} text2 Second string.
	 * @return {Array.<string>?} Five element Array, containing the prefix of
	 *     text1, the suffix of text1, the prefix of text2, the suffix of
	 *     text2 and the common middle.  Or null if there was no match.
	 */
	function diff_halfMatch($text1, $text2) {
		$longtext = mb_strlen($text1) > mb_strlen($text2) ? $text1 : $text2;
		$shorttext = mb_strlen($text1) > mb_strlen($text2) ? $text2 : $text1;
		if (mb_strlen($longtext) < 10 || mb_strlen($shorttext) < 1) {
			return null; // Pointless.
		}

		// First check if the second quarter is the seed for a half-match.
		$hm1 = $this->diff_halfMatchI($longtext, $shorttext, ceil(mb_strlen($longtext) / 4));
		// Check again based on the third quarter.
		$hm2 = $this->diff_halfMatchI($longtext, $shorttext, ceil(mb_strlen($longtext) / 2));

		if (!$hm1 && !$hm2) {
			return null;
		} elseif (!$hm2) {
			$hm = $hm1;
		} elseif (!$hm1) {
			$hm = $hm2;
		} else {
			// Both matched.  Select the longest.
			$hm = mb_strlen($hm1[4]) > mb_strlen($hm2[4]) ? $hm1 : $hm2;
		}

		// A half-match was found, sort out the return data.
		if (mb_strlen($text1) > mb_strlen($text2)) {
			$text1_a = $hm[0];
			$text1_b = $hm[1];
			$text2_a = $hm[2];
			$text2_b = $hm[3];
		} else {
			$text2_a = $hm[0];
			$text2_b = $hm[1];
			$text1_a = $hm[2];
			$text1_b = $hm[3];
		}
		$mid_common = $hm[4];
		return array( $text1_a, $text1_b, $text2_a, $text2_b, $mid_common );
	}

	/**
	 * Does a mb_substring of shorttext exist within longtext such that the mb_substring
	 * is at least half the length of longtext?
	 * Closure, but does not reference any external variables.
	 * @param {string} longtext Longer string.
	 * @param {string} shorttext Shorter string.
	 * @param {number} i Start index of quarter length mb_substring within longtext
	 * @return {Array.<string>?} Five element Array, containing the prefix of
	 *     longtext, the suffix of longtext, the prefix of shorttext, the suffix
	 *     of shorttext and the common middle.  Or null if there was no match.
	 * @private
	 */
	function diff_halfMatchI($longtext, $shorttext, $i) {
		// Start with a 1/4 length mb_substring at position i as a seed.
		$seed = mb_substr($longtext, $i, floor(mb_strlen($longtext) / 4));

		$j = -1;
		$best_common = '';
		$best_longtext_a = null;
		$best_longtext_b = null;
		$best_shorttext_a = null;
		$best_shorttext_b = null;
		while ( ($j = mb_strpos($shorttext, $seed, $j + 1)) !== false ) {
			$prefixLength = $this->diff_commonPrefix(mb_substr($longtext, $i), mb_substr($shorttext, $j));
			$suffixLength = $this->diff_commonSuffix(mb_substr($longtext, 0, $i), mb_substr($shorttext, 0, $j));
			if (mb_strlen($best_common) < $suffixLength + $prefixLength) {
				$best_common = mb_substr($shorttext, $j - $suffixLength, $suffixLength) . mb_substr($shorttext, $j, $prefixLength);
				$best_longtext_a = mb_substr($longtext, 0, $i - $suffixLength);
				$best_longtext_b = mb_substr($longtext, $i + $prefixLength);
				$best_shorttext_a = mb_substr($shorttext, 0, $j - $suffixLength);
				$best_shorttext_b = mb_substr($shorttext, $j + $prefixLength);
			}
		}
		if (mb_strlen($best_common) >= mb_strlen($longtext) / 2) {
			return array (
				$best_longtext_a,
				$best_longtext_b,
				$best_shorttext_a,
				$best_shorttext_b,
				$best_common
			);
		} else {
			return null;
		}
	}

	/**
	 * Reduce the number of edits by eliminating semantically trivial equalities.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 */
	function diff_cleanupSemantic(&$diffs) {
		$changes = false;
		$equalities = array (); // Stack of indices where equalities are found.
		$equalitiesLength = 0; // Keeping our own length var is faster in JS.
		$lastequality = null; // Always equal to equalities[equalitiesLength-1][1]
		$pointer = 0; // Index of current position.
		// Number of characters that changed prior to the equality.
		$length_changes1 = 0;
		// Number of characters that changed after the equality.
		$length_changes2 = 0;
		while ($pointer < count($diffs)) {
			if ($diffs[$pointer][0] == DIFF_EQUAL) { // equality found
				$equalities[$equalitiesLength++] = $pointer;
				$length_changes1 = $length_changes2;
				$length_changes2 = 0;
				$lastequality = $diffs[$pointer][1];
			} else { // an insertion or deletion
				$length_changes2 += mb_strlen($diffs[$pointer][1]);
				if ($lastequality !== null && (mb_strlen($lastequality) <= $length_changes1) && (mb_strlen($lastequality) <= $length_changes2)) {
					// Duplicate record
					$zzz_diffs = array_splice($diffs, $equalities[$equalitiesLength -1], 0, array(array (
						DIFF_DELETE,
						$lastequality
					)));
					// Change second copy to insert.
					$diffs[$equalities[$equalitiesLength -1] + 1][0] = DIFF_INSERT;
					// Throw away the equality we just deleted.
					$equalitiesLength--;
					// Throw away the previous equality (it needs to be reevaluated).
					$equalitiesLength--;
					$pointer = $equalitiesLength > 0 ? $equalities[$equalitiesLength -1] : -1;
					$length_changes1 = 0; // Reset the counters.
					$length_changes2 = 0;
					$lastequality = null;
					$changes = true;
				}
			}
			$pointer++;
		}
		if ($changes) {
			$this->diff_cleanupMerge($diffs);
		}
		$this->diff_cleanupSemanticLossless($diffs);
	}

	/**
	 * Look for single edits surrounded on both sides by equalities
	 * which can be shifted sideways to align the edit to a word boundary.
	 * e.g: The c<ins>at c</ins>ame. -> The <ins>cat </ins>came.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 */
	function diff_cleanupSemanticLossless(&$diffs) {

		$pointer = 1;
		// Intentionally ignore the first and last element (don't need checking).
		while ($pointer < count($diffs) - 1) {
			if ($diffs[$pointer -1][0] == DIFF_EQUAL && $diffs[$pointer +1][0] == DIFF_EQUAL) {
				// This is a single edit surrounded by equalities.
				$equality1 = $diffs[$pointer -1][1];
				$edit = $diffs[$pointer][1];
				$equality2 = $diffs[$pointer +1][1];

				// First, shift the edit as far left as possible.
				$commonOffset = $this->diff_commonSuffix($equality1, $edit);
				if ($commonOffset !== '') {
					$commonString = mb_substr($edit, mb_strlen($edit) - $commonOffset);
					$equality1 = mb_substr($equality1, 0, mb_strlen($equality1) - $commonOffset);
					$edit = $commonString . mb_substr($edit, 0, mb_strlen($edit) - $commonOffset);
					$equality2 = $commonString . $equality2;
				}

				// Second, step character by character right, looking for the best fit.
				$bestEquality1 = $equality1;
				$bestEdit = $edit;
				$bestEquality2 = $equality2;
				$bestScore = $this->diff_cleanupSemanticScore($equality1, $edit) + $this->diff_cleanupSemanticScore($edit, $equality2);
				while (isset($equality2[0]) && $edit[0] === $equality2[0]) {
					$equality1 .= $edit[0];
					$edit = mb_substr($edit, 1) . $equality2[0];
					$equality2 = mb_substr($equality2, 1);
					$score = $this->diff_cleanupSemanticScore($equality1, $edit) + $this->diff_cleanupSemanticScore($edit, $equality2);
					// The >= encourages trailing rather than leading whitespace on edits.
					if ($score >= $bestScore) {
						$bestScore = $score;
						$bestEquality1 = $equality1;
						$bestEdit = $edit;
						$bestEquality2 = $equality2;
					}
				}

				if ($diffs[$pointer -1][1] != $bestEquality1) {
					// We have an improvement, save it back to the diff.
					if ($bestEquality1) {
						$diffs[$pointer -1][1] = $bestEquality1;
					} else {
						$zzz_diffs = array_splice($diffs, $pointer -1, 1);
						$pointer--;
					}
					$diffs[$pointer][1] = $bestEdit;
					if ($bestEquality2) {
						$diffs[$pointer +1][1] = $bestEquality2;
					} else {
						$zzz_diffs = array_splice($diffs, $pointer +1, 1);
						$pointer--;
					}
				}
			}
			$pointer++;
		}
	}

	/**
	 * Given two strings, compute a score representing whether the internal
	 * boundary falls on logical boundaries.
	 * Scores range from 5 (best) to 0 (worst).
	 * Closure, makes reference to regex patterns defined above.
	 * @param {string} one First string
	 * @param {string} two Second string
	 * @return {number} The score.
	 */
	function diff_cleanupSemanticScore($one, $two) {
		// Define some regex patterns for matching boundaries.
		$punctuation = '/[^a-zA-Z0-9]/';
		$whitespace = '/\s/';
		$linebreak = '/[\r\n]/';
		$blanklineEnd = '/\n\r?\n$/';
		$blanklineStart = '/^\r?\n\r?\n/';

		if (!$one || !$two) {
			// Edges are the best.
			return 5;
		}

		// Each port of this function behaves slightly differently due to
		// subtle differences in each language's definition of things like
		// 'whitespace'.  Since this function's purpose is largely cosmetic,
		// the choice has been made to use each language's native features
		// rather than force total conformity.
		$score = 0;
		// One point for non-alphanumeric.
		if (preg_match($punctuation, $one[mb_strlen($one) - 1]) || preg_match($punctuation, $two[0])) {
			$score++;
			// Two points for whitespace.
			if (preg_match($whitespace, $one[mb_strlen($one) - 1] ) || preg_match($whitespace, $two[0])) {
				$score++;
				// Three points for line breaks.
				if (preg_match($linebreak, $one[mb_strlen($one) - 1]) || preg_match($linebreak, $two[0])) {
					$score++;
					// Four points for blank lines.
					if (preg_match($blanklineEnd, $one) || preg_match($blanklineStart, $two)) {
						$score++;
					}
				}
			}
		}
		return $score;
	}

	/**
	 * Reduce the number of edits by eliminating operationally trivial equalities.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 */
	function diff_cleanupEfficiency(&$diffs) {
		$changes = false;
		$equalities = array (); // Stack of indices where equalities are found.
		$equalitiesLength = 0; // Keeping our own length var is faster in JS.
		$lastequality = ''; // Always equal to equalities[equalitiesLength-1][1]
		$pointer = 0; // Index of current position.
		// Is there an insertion operation before the last equality.
		$pre_ins = false;
		// Is there a deletion operation before the last equality.
		$pre_del = false;
		// Is there an insertion operation after the last equality.
		$post_ins = false;
		// Is there a deletion operation after the last equality.
		$post_del = false;
		while ($pointer < count($diffs)) {
			if ($diffs[$pointer][0] == DIFF_EQUAL) { // equality found
				if (mb_strlen($diffs[$pointer][1]) < $this->Diff_EditCost && ($post_ins || $post_del)) {
					// Candidate found.
					$equalities[$equalitiesLength++] = $pointer;
					$pre_ins = $post_ins;
					$pre_del = $post_del;
					$lastequality = $diffs[$pointer][1];
				} else {
					// Not a candidate, and can never become one.
					$equalitiesLength = 0;
					$lastequality = '';
				}
				$post_ins = $post_del = false;
			} else { // an insertion or deletion
				if ($diffs[$pointer][0] == DIFF_DELETE) {
					$post_del = true;
				} else {
					$post_ins = true;
				}
				/*
				 * Five types to be split:
				 * <ins>A</ins><del>B</del>XY<ins>C</ins><del>D</del>
				 * <ins>A</ins>X<ins>C</ins><del>D</del>
				 * <ins>A</ins><del>B</del>X<ins>C</ins>
				 * <ins>A</del>X<ins>C</ins><del>D</del>
				 * <ins>A</ins><del>B</del>X<del>C</del>
				 */
				if ($lastequality && (($pre_ins && $pre_del && $post_ins && $post_del) || ((mb_strlen($lastequality) < $this->Diff_EditCost / 2) && ($pre_ins + $pre_del + $post_ins + $post_del) == 3))) {
					// Duplicate record
					$zzz_diffs = array_splice($diffs, $equalities[$equalitiesLength -1], 0, array(array (
						DIFF_DELETE,
						$lastequality
					)));
					// Change second copy to insert.
					$diffs[$equalities[$equalitiesLength -1] + 1][0] = DIFF_INSERT;
					$equalitiesLength--; // Throw away the equality we just deleted;
					$lastequality = '';
					if ($pre_ins && $pre_del) {
						// No changes made which could affect previous entry, keep going.
						$post_ins = $post_del = true;
						$equalitiesLength = 0;
					} else {
						$equalitiesLength--; // Throw away the previous equality;
						$pointer = $equalitiesLength > 0 ? $equalities[$equalitiesLength -1] : -1;
						$post_ins = $post_del = false;
					}
					$changes = true;
				}
			}
			$pointer++;
		}

		if ($changes) {
			$this->diff_cleanupMerge($diffs);
		}
	}

	/**
	 * Reorder and merge like edit sections.  Merge equalities.
	 * Any edit section can move as long as it doesn't cross an equality.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 */
	function diff_cleanupMerge(&$diffs) {
		array_push($diffs, array ( DIFF_EQUAL, '' )); // Add a dummy entry at the end.
		$pointer = 0;
		$count_delete = 0;
		$count_insert = 0;
		$text_delete = '';
		$text_insert = '';
		$commonlength = null;
		while ($pointer < count($diffs)) {
			switch ($diffs[$pointer][0]) {
				case DIFF_INSERT :
					$count_insert++;
					$text_insert .= $diffs[$pointer][1];
					$pointer++;
					break;
				case DIFF_DELETE :
					$count_delete++;
					$text_delete .= $diffs[$pointer][1];
					$pointer++;
					break;
				case DIFF_EQUAL :
					// Upon reaching an equality, check for prior redundancies.
					if ($count_delete !== 0 || $count_insert !== 0) {
						if ($count_delete !== 0 && $count_insert !== 0) {
							// Factor out any common prefixies.
							$commonlength = $this->diff_commonPrefix($text_insert, $text_delete);
							if ($commonlength !== 0) {
								if (($pointer - $count_delete - $count_insert) > 0 && $diffs[$pointer - $count_delete - $count_insert -1][0] == DIFF_EQUAL) {
									$diffs[$pointer - $count_delete - $count_insert -1][1] .= mb_substr($text_insert, 0, $commonlength);
								} else {
									array_splice($diffs, 0, 0, array(array (
										DIFF_EQUAL,
										mb_substr($text_insert, 0, $commonlength)
									)));
									$pointer++;
								}
								$text_insert = mb_substr($text_insert, $commonlength);
								$text_delete = mb_substr($text_delete, $commonlength);
							}
							// Factor out any common suffixies.
							$commonlength = $this->diff_commonSuffix($text_insert, $text_delete);
							if ($commonlength !== 0) {
								$diffs[$pointer][1] = mb_substr($text_insert, mb_strlen($text_insert) - $commonlength) . $diffs[$pointer][1];
								$text_insert = mb_substr($text_insert, 0, mb_strlen($text_insert) - $commonlength);
								$text_delete = mb_substr($text_delete, 0, mb_strlen($text_delete) - $commonlength);
							}
						}
						// Delete the offending records and add the merged ones.
						if ($count_delete === 0) {
							array_splice($diffs, $pointer-$count_delete-$count_insert, $count_delete+$count_insert, array(array(
								DIFF_INSERT,
								$text_insert
							)));
						} elseif ($count_insert === 0) {
							array_splice($diffs, $pointer-$count_delete-$count_insert, $count_delete+$count_insert, array(array(
								DIFF_DELETE,
								$text_delete
							)));
						} else {
							array_splice($diffs, $pointer-$count_delete-$count_insert, $count_delete+$count_insert, array(array(
								DIFF_DELETE,
								$text_delete
							), array (
								DIFF_INSERT,
								$text_insert
							)));
						}
						$pointer = $pointer - $count_delete - $count_insert + ($count_delete ? 1 : 0) + ($count_insert ? 1 : 0) + 1;
					} elseif ($pointer !== 0 && $diffs[$pointer -1][0] == DIFF_EQUAL) {
						// Merge this equality with the previous one.
						$diffs[$pointer -1][1] .= $diffs[$pointer][1];
						array_splice($diffs, $pointer, 1);
					} else {
						$pointer++;
					}
					$count_insert = 0;
					$count_delete = 0;
					$text_delete = '';
					$text_insert = '';
					break;
			}
		}
		if ($diffs[count($diffs) - 1][1] === '') {
			array_pop($diffs); // Remove the dummy entry at the end.
		}

		// Second pass: look for single edits surrounded on both sides by equalities
		// which can be shifted sideways to eliminate an equality.
		// e.g: A<ins>BA</ins>C -> <ins>AB</ins>AC
		$changes = false;
		$pointer = 1;
		// Intentionally ignore the first and last element (don't need checking).
		while ($pointer < count($diffs) - 1) {
			if ($diffs[$pointer-1][0] == DIFF_EQUAL && $diffs[$pointer+1][0] == DIFF_EQUAL) {
				// This is a single edit surrounded by equalities.
				if ( mb_substr($diffs[$pointer][1], mb_strlen($diffs[$pointer][1]) - mb_strlen($diffs[$pointer -1][1])) == $diffs[$pointer -1][1]) {
					// Shift the edit over the previous equality.
					$diffs[$pointer][1] = $diffs[$pointer -1][1] . mb_substr($diffs[$pointer][1], 0, mb_strlen($diffs[$pointer][1]) - mb_strlen($diffs[$pointer -1][1]));
					$diffs[$pointer +1][1] = $diffs[$pointer -1][1] . $diffs[$pointer +1][1];
					array_splice($diffs, $pointer -1, 1);
					$changes = true;
				} elseif (mb_substr($diffs[$pointer][1], 0, mb_strlen($diffs[$pointer +1][1])) == $diffs[$pointer +1][1]) {
					// Shift the edit over the next equality.
					$diffs[$pointer -1][1] .= $diffs[$pointer +1][1];

					$diffs[$pointer][1] = mb_substr($diffs[$pointer][1], mb_strlen($diffs[$pointer +1][1])) . $diffs[$pointer +1][1];
					array_splice($diffs, $pointer +1, 1);
					$changes = true;
				}
			}
			$pointer++;
		}
		// If shifts were made, the diff needs reordering and another shift sweep.
		if ($changes) {
			$this->diff_cleanupMerge($diffs);
		}
	}

	/**
	 * loc is a location in text1, compute and return the equivalent location in
	 * text2.
	 * e.g. 'The cat' vs 'The big cat', 1->1, 5->8
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @param {number} loc Location within text1.
	 * @return {number} Location within text2.
	 */
	function diff_xIndex($diffs, $loc) {
		$chars1 = 0;
		$chars2 = 0;
		$last_chars1 = 0;
		$last_chars2 = 0;
		for ($x = 0; $x < count($diffs); $x++) {
			if ($diffs[$x][0] !== DIFF_INSERT) { // Equality or deletion.
				$chars1 += mb_strlen($diffs[$x][1]);
			}
			if ($diffs[$x][0] !== DIFF_DELETE) { // Equality or insertion.
				$chars2 += mb_strlen($diffs[$x][1]);
			}
			if ($chars1 > $loc) { // Overshot the location.
				break;
			}
			$last_chars1 = $chars1;
			$last_chars2 = $chars2;
		}
		// Was the location was deleted?
		if (count($diffs) != $x && $diffs[$x][0] === DIFF_DELETE) {
			return $last_chars2;
		}
		// Add the remaining character length.
		return $last_chars2 + ($loc - $last_chars1);
	}

	/**
	 * Convert a diff array into a pretty HTML report.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @return {string} HTML representation.
	 */
	function diff_prettyHtml($diffs) {
		$html = array ();
		$i = 0;
		for ($x = 0; $x < count($diffs); $x++) {
			$op = $diffs[$x][0]; // Operation (insert, delete, equal)
			$data = $diffs[$x][1]; // Text of change.
			$text = preg_replace(array (
				'/&/',
				'/</',
				'/>/',
				"/\n/"
			), array (
				'&amp;',
				'&lt;',
				'&gt;',
				'&para;<BR>'
			), $data);

			switch ($op) {
				case DIFF_INSERT :
					$html[$x] = '<INS STYLE="background:#E6FFE6;" TITLE="i=' . $i . '">' . $text . '</INS>';
					break;
				case DIFF_DELETE :
					$html[$x] = '<DEL STYLE="background:#FFE6E6;" TITLE="i=' . $i . '">' . $text . '</DEL>';
					break;
				case DIFF_EQUAL :
					$html[$x] = '<SPAN TITLE="i=' . $i . '">' . $text . '</SPAN>';
					break;
			}
			if ($op !== DIFF_DELETE) {
				$i += mb_strlen($data);
			}
		}
		return implode('',$html);
	}

	/**
	 * Compute and return the source text (all equalities and deletions).
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @return {string} Source text.
	 */
	function diff_text1($diffs) {
		$text = array ();
		for ($x = 0; $x < count($diffs); $x++) {
			if ($diffs[$x][0] !== DIFF_INSERT) {
				$text[$x] = $diffs[$x][1];
			}
		}
		return implode('',$text);
	}

	/**
	 * Compute and return the destination text (all equalities and insertions).
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @return {string} Destination text.
	 */
	function diff_text2($diffs) {
		$text = array ();
		for ($x = 0; $x < count($diffs); $x++) {
			if ($diffs[$x][0] !== DIFF_DELETE) {
				$text[$x] = $diffs[$x][1];
			}
		}
		return implode('',$text);
	}

	/**
	 * Compute the Levenshtein distance; the number of inserted, deleted or
	 * substituted characters.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @return {number} Number of changes.
	 */
	function diff_levenshtein($diffs) {
		$levenshtein = 0;
		$insertions = 0;
		$deletions = 0;
		for ($x = 0; $x < count($diffs); $x++) {
			$op = $diffs[$x][0];
			$data = $diffs[$x][1];
			switch ($op) {
				case DIFF_INSERT :
					$insertions += mb_strlen($data);
					break;
				case DIFF_DELETE :
					$deletions += mb_strlen($data);
					break;
				case DIFF_EQUAL :
					// A deletion and an insertion is one substitution.
					$levenshtein += max($insertions, $deletions);
					$insertions = 0;
					$deletions = 0;
					break;
			}
		}
		$levenshtein += max($insertions, $deletions);
		return $levenshtein;
	}

	/**
	 * Crush the diff into an encoded string which describes the operations
	 * required to transform text1 into text2.
	 * E.g. =3\t-2\t+ing  -> Keep 3 chars, delete 2 chars, insert 'ing'.
	 * Operations are tab-separated.  Inserted text is escaped using %xx notation.
	 * @param {Array.<Array.<number|string>>} diffs Array of diff tuples.
	 * @return {string} Delta text.
	 */
	function diff_toDelta($diffs) {
		$text = array ();
		for ($x = 0; $x < count($diffs); $x++) {
			switch ($diffs[$x][0]) {
				case DIFF_INSERT :
					$text[$x] = '+' .encodeURI($diffs[$x][1]);
					break;
				case DIFF_DELETE :
					$text[$x] = '-' .mb_strlen($diffs[$x][1]);
					break;
				case DIFF_EQUAL :
					$text[$x] = '=' .mb_strlen($diffs[$x][1]);
					break;
			}
		}
		return str_replace('%20', ' ', implode("\t", $text));
	}

	/**
	 * Given the original text1, and an encoded string which describes the
	 * operations required to transform text1 into text2, compute the full diff.
	 * @param {string} text1 Source string for the diff.
	 * @param {string} delta Delta text.
	 * @return {Array.<Array.<number|string>>} Array of diff tuples.
	 * @throws {Error} If invalid input.
	 */
	function diff_fromDelta($text1, $delta) {
		$diffs = array ();
		$diffsLength = 0; // Keeping our own length var is faster in JS.
		$pointer = 0; // Cursor in text1
		$tokens = preg_split("/\t/", $delta);

		for ($x = 0; $x < count($tokens); $x++) {
			// Each token begins with a one character parameter which specifies the
			// operation of this token (delete, insert, equality).
			$param = mb_substr($tokens[$x], 1);
			switch ($tokens[$x][0]) {
				case '+' :
					try {
						$diffs[$diffsLength++] = array (
							DIFF_INSERT,
							decodeURI($param)
						);
					} catch (Exception $ex) {
						echo_Exception('Illegal escape in diff_fromDelta: ' . $param);
						// Malformed URI sequence.
					}
					break;
				case '-' :
					// Fall through.
				case '=' :
					$n = (int) $param;
					if ($n < 0) {
						echo_Exception('Invalid number in diff_fromDelta: ' . $param);
					}
					$text = mb_substr($text1, $pointer, $n);
					$pointer += $n;
					if ($tokens[$x][0] == '=') {
						$diffs[$diffsLength++] = array (
							DIFF_EQUAL,
							$text
						);
					} else {
						$diffs[$diffsLength++] = array (
							DIFF_DELETE,
							$text
						);
					}
					break;
				default :
					// Blank tokens are ok (from a trailing \t).
					// Anything else is an error.
					if ($tokens[$x]) {
						echo_Exception('Invalid diff operation in diff_fromDelta: ' . $tokens[$x]);
					}
			}
		}
		if ($pointer != mb_strlen($text1)) {
//			throw new Exception('Delta length (' . $pointer . ') does not equal source text length (' . mb_strlen($text1) . ').');
			echo_Exception('Delta length (' . $pointer . ') does not equal source text length (' . mb_strlen($text1) . ').');
		}
		return $diffs;
	}

	//  MATCH FUNCTIONS

	/**
	 * Locate the best instance of 'pattern' in 'text' near 'loc'.
	 * @param {string} text The text to search.
	 * @param {string} pattern The pattern to search for.
	 * @param {number} loc The location to search around.
	 * @return {number} Best match index or -1.
	 */
	function match_main($text, $pattern, $loc) {
		$loc = max(0, min($loc, mb_strlen($text)));
		if ($text == $pattern) {
			// Shortcut (potentially not guaranteed by the algorithm)
			return 0;
		}
		elseif (!mb_strlen($text)) {
			// Nothing to match.
			return -1;
		}
		elseif (mb_substr($text, $loc, mb_strlen($pattern)) == $pattern) {
			// Perfect match at the perfect spot!  (Includes case of null pattern)
			return $loc;
		} else {
			// Do a fuzzy compare.
			return $this->match_bitap($text, $pattern, $loc);
		}
	}

	/**
	 * Locate the best instance of 'pattern' in 'text' near 'loc' using the
	 * Bitap algorithm.
	 * @param {string} text The text to search.
	 * @param {string} pattern The pattern to search for.
	 * @param {number} loc The location to search around.
	 * @return {number} Best match index or -1.
	 * @private
	 */
	function match_bitap($text, $pattern, $loc) {
		if (mb_strlen($pattern) > Match_MaxBits) {
			echo_Exception('Pattern too long for this browser.');
		}

		// Initialise the alphabet.
		$s = $this->match_alphabet($pattern);

		// Highest score beyond which we give up.
		$score_threshold = $this->Match_Threshold;

		// Is there a nearby exact match? (speedup)
		$best_loc = mb_strpos($text, $pattern, $loc);
		if ($best_loc !== false) {
			$score_threshold = min($this->match_bitapScore(0, $best_loc, $pattern, $loc), $score_threshold);
		}

		// What about in the other direction? (speedup)
		$best_loc = mb_strrpos( $text, $pattern, min($loc + mb_strlen($pattern), mb_strlen($text)) );
		if ($best_loc !== false) {
			$score_threshold = min($this->match_bitapScore(0, $best_loc, $pattern, $loc), $score_threshold);
		}

		// Initialise the bit arrays.
		$matchmask = 1 << (mb_strlen($pattern) - 1);
		$best_loc = -1;

		$bin_min = null;
		$bin_mid = null;
		$bin_max = mb_strlen($pattern) + mb_strlen($text);
		$last_rd = null;
		for ($d = 0; $d < mb_strlen($pattern); $d++) {
			// Scan for the best match; each iteration allows for one more error.
			// Run a binary search to determine how far from 'loc' we can stray at this
			// error level.
			$bin_min = 0;
			$bin_mid = $bin_max;
			while ($bin_min < $bin_mid) {
				if ($this->match_bitapScore($d, $loc + $bin_mid, $pattern, $loc) <= $score_threshold) {
					$bin_min = $bin_mid;
				} else {
					$bin_max = $bin_mid;
				}
				$bin_mid = floor(($bin_max - $bin_min) / 2 + $bin_min);
			}
			// Use the result from this iteration as the maximum for the next.
			$bin_max = $bin_mid;
			$start = max(1, $loc - $bin_mid +1);
			$finish = min($loc + $bin_mid, mb_strlen($text)) + mb_strlen($pattern);

			$rd = Array (
				$finish +2
			);
			$rd[$finish +1] = (1 << $d) - 1;
			for ($j = $finish; $j >= $start; $j--) {
				// The alphabet (s) is a sparse hash, so the following line generates
				// warnings.
@				$charMatch = $s[ $text[$j -1] ];
				if ($d === 0) { // First pass: exact match.
					$rd[$j] = (($rd[$j +1] << 1) | 1) & $charMatch;
				} else { // Subsequent passes: fuzzy match.
					$rd[$j] = (($rd[$j +1] << 1) | 1) & $charMatch | ((($last_rd[$j +1] | $last_rd[$j]) << 1) | 1) | $last_rd[$j +1];
				}
				if ($rd[$j] & $matchmask) {
					$score = $this->match_bitapScore($d, $j -1, $pattern, $loc);
					// This match will almost certainly be better than any existing match.
					// But check anyway.
					if ($score <= $score_threshold) {
						// Told you so.
						$score_threshold = $score;
						$best_loc = $j -1;
						if ($best_loc > $loc) {
							// When passing loc, don't exceed our current distance from loc.
							$start = max(1, 2 * $loc - $best_loc);
						} else {
							// Already passed loc, downhill from here on in.
							break;
						}
					}
				}
			}
			// No hope for a (better) match at greater error levels.
			if ($this->match_bitapScore($d +1, $loc, $pattern, $loc) > $score_threshold) {
				break;
			}
			$last_rd = $rd;
		}
		return (int)$best_loc;
	}

	/**
	 * Compute and return the score for a match with e errors and x location.
	 * Accesses loc and pattern through being a closure.
	 * @param {number} e Number of errors in match.
	 * @param {number} x Location of match.
	 * @return {number} Overall score for match (0.0 = good, 1.0 = bad).
	 * @private
	 */
	function match_bitapScore($e, $x, $pattern, $loc) {
		$accuracy = $e / mb_strlen($pattern);
		$proximity = abs($loc - $x);
		if (!$this->Match_Distance) {
			// Dodge divide by zero error.
			return $proximity ? 1.0 : $accuracy;
		}
		return $accuracy + ($proximity / $this->Match_Distance);
	}

	/**
	 * Initialise the alphabet for the Bitap algorithm.
	 * @param {string} pattern The text to encode.
	 * @return {Object} Hash of character locations.
	 * @private
	 */
	function match_alphabet($pattern) {
		$s = array ();
		for ($i = 0; $i < mb_strlen($pattern); $i++) {
			$s[ $pattern[$i] ] = 0;
		}
		for ($i = 0; $i < mb_strlen($pattern); $i++) {
			$s[ $pattern[$i] ] |= 1 << (mb_strlen($pattern) - $i - 1);
		}
		return $s;
	}

	//  PATCH FUNCTIONS

	/**
	 * Increase the context until it is unique,
	 * but don't let the pattern expand beyond Match_MaxBits.
	 * @param {patch_obj} patch The patch to grow.
	 * @param {string} text Source text.
	 * @private
	 */
	function patch_addContext($patch, $text) {
		$pattern = mb_substr($text, $patch->start2, $patch->length1 );
		$previousPattern = null;
		$padding = 0;
		$i = 0;
		while (
			(   mb_strlen($pattern) === 0 // Javascript's indexOf/lastIndexOd return 0/strlen respectively if pattern = ''
			 || mb_strpos($text, $pattern) !== mb_strrpos($text, $pattern)
			)
			&& $pattern !== $previousPattern // avoid infinte loop
			&& mb_strlen($pattern) < Match_MaxBits - $this->Patch_Margin - $this->Patch_Margin ) {
			$padding += $this->Patch_Margin;
			$previousPattern = $pattern;
			$pattern = mb_substr($text, max($patch->start2 - $padding,0), ($patch->start2 + $patch->length1 + $padding) - max($patch->start2 - $padding,0) );
		}
		// Add one chunk for good luck.
		$padding += $this->Patch_Margin;
		// Add the prefix.
		$prefix = mb_substr($text, max($patch->start2 - $padding,0), $patch->start2 - max($patch->start2 - $padding,0) );
		if ($prefix!=='') {
			array_unshift($patch->diffs, array (
				DIFF_EQUAL,
				$prefix
			));
		}
		// Add the suffix.
		$suffix = mb_substr($text, $patch->start2 + $patch->length1, ($patch->start2 + $patch->length1 + $padding) - ($patch->start2 + $patch->length1) );
		if ($suffix!=='') {
			array_push($patch->diffs, array (
				DIFF_EQUAL,
				$suffix
			));
		}

		// Roll back the start points.
		$patch->start1 -= mb_strlen($prefix);
		$patch->start2 -= mb_strlen($prefix);
		// Extend the lengths.
		$patch->length1 += mb_strlen($prefix) + mb_strlen($suffix);
		$patch->length2 += mb_strlen($prefix) + mb_strlen($suffix);
	}

	/**
	 * Compute a list of patches to turn text1 into text2.
	 * Use diffs if provided, otherwise compute it ourselves.
	 * There are four ways to call this function, depending on what data is
	 * available to the caller:
	 * Method 1:
	 * a = text1, b = text2
	 * Method 2:
	 * a = diffs
	 * Method 3 (optimal):
	 * a = text1, b = diffs
	 * Method 4 (deprecated, use method 3):
	 * a = text1, b = text2, c = diffs
	 *
	 * @param {string|Array.<Array.<number|string>>} a text1 (methods 1,3,4) or
	 * Array of diff tuples for text1 to text2 (method 2).
	 * @param {string|Array.<Array.<number|string>>} opt_b text2 (methods 1,4) or
	 * Array of diff tuples for text1 to text2 (method 3) or undefined (method 2).
	 * @param {string|Array.<Array.<number|string>>} opt_c Array of diff tuples for
	 * text1 to text2 (method 4) or undefined (methods 1,2,3).
	 * @return {Array.<patch_obj>} Array of patch objects.
	 */
	function patch_make($a, $opt_b = null, $opt_c = null ) {
		if (is_string($a) && is_string($opt_b) && $opt_c === null ) {
			// Method 1: text1, text2
			// Compute diffs from text1 and text2.
			$text1 = $a;
			$diffs = $this->diff_main($text1, $opt_b, true);
			if ( count($diffs) > 2) {
				$this->diff_cleanupSemantic($diffs);
				$this->diff_cleanupEfficiency($diffs);
			}
		} elseif ( is_array($a) && $opt_b === null && $opt_c === null) {
			// Method 2: diffs
			// Compute text1 from diffs.
			$diffs = $a;
			$text1 = $this->diff_text1($diffs);
		} elseif ( is_string($a) && is_array($opt_b) && $opt_c === null) {
			// Method 3: text1, diffs
			$text1 = $a;
			$diffs = $opt_b;
		} elseif ( is_string($a) && is_string($opt_b) && is_array($opt_c) ) {
			// Method 4: text1, text2, diffs
			// text2 is not used.
			$text1 = $a;
			$diffs = $opt_c;
		} else {
			echo_Exception('Unknown call format to patch_make.');
		}

		if ( count($diffs) === 0) {
			return array(); // Get rid of the null case.
		}
		$patches = array();
		$patch = new patch_obj();
		$patchDiffLength = 0; // Keeping our own length var is faster in JS.
		$char_count1 = 0; // Number of characters into the text1 string.
		$char_count2 = 0; // Number of characters into the text2 string.
		// Start with text1 (prepatch_text) and apply the diffs until we arrive at
		// text2 (postpatch_text).  We recreate the patches one by one to determine
		// context info.
		$prepatch_text = $text1;
		$postpatch_text = $text1;
		for ($x = 0; $x < count($diffs); $x++) {
			$diff_type = $diffs[$x][0];
			$diff_text = $diffs[$x][1];

			if (!$patchDiffLength && $diff_type !== DIFF_EQUAL) {
				// A new patch starts here.
				$patch->start1 = $char_count1;
				$patch->start2 = $char_count2;
			}

			switch ($diff_type) {
				case DIFF_INSERT :
					$patch->diffs[$patchDiffLength++] = $diffs[$x];
					$patch->length2 += mb_strlen($diff_text);
					$postpatch_text = mb_substr($postpatch_text, 0, $char_count2) . $diff_text . mb_substr($postpatch_text,$char_count2);
					break;
				case DIFF_DELETE :
					$patch->length1 += mb_strlen($diff_text);
					$patch->diffs[$patchDiffLength++] = $diffs[$x];
					$postpatch_text = mb_substr($postpatch_text, 0, $char_count2) . mb_substr($postpatch_text, $char_count2 + mb_strlen($diff_text) );
					break;
				case DIFF_EQUAL :
					if ( mb_strlen($diff_text) <= 2 * $this->Patch_Margin && $patchDiffLength && count($diffs) != $x + 1) {
						// Small equality inside a patch.
						$patch->diffs[$patchDiffLength++] = $diffs[$x];
						$patch->length1 += mb_strlen($diff_text);
						$patch->length2 += mb_strlen($diff_text);
					} elseif ( mb_strlen($diff_text) >= 2 * $this->Patch_Margin ) {
						// Time for a new patch.
						if ($patchDiffLength) {
							$this->patch_addContext($patch, $prepatch_text);
							array_push($patches,$patch);
							$patch = new patch_obj();
							$patchDiffLength = 0;
							// Unlike Unidiff, our patch lists have a rolling context.
							// http://code.google.com/p/google-diff-match-patch/wiki/Unidiff
							// Update prepatch text & pos to reflect the application of the
							// just completed patch.
							$prepatch_text = $postpatch_text;
							$char_count1 = $char_count2;
						}
					}
					break;
			}

			// Update the current character count.
			if ($diff_type !== DIFF_INSERT) {
				$char_count1 += mb_strlen($diff_text);
			}
			if ($diff_type !== DIFF_DELETE) {
				$char_count2 += mb_strlen($diff_text);
			}
		}
		// Pick up the leftover patch if not empty.
		if ($patchDiffLength) {
			$this->patch_addContext($patch, $prepatch_text);
			array_push($patches, $patch);
		}

		return $patches;
	}

	/**
	 * Given an array of patches, return another array that is identical.
	 * @param {Array.<patch_obj>} patches Array of patch objects.
	 * @return {Array.<patch_obj>} Array of patch objects.
	 */
	function patch_deepCopy($patches) {
		// Making deep copies is hard in JavaScript.
		$patchesCopy = array();
		for ($x = 0; $x < count($patches); $x++) {
			$patch = $patches[$x];
			$patchCopy = new patch_obj();
			for ($y = 0; $y < count($patch->diffs); $y++) {
				$patchCopy->diffs[$y] = $patch->diffs[$y]; // ?? . slice();
			}
			$patchCopy->start1 = $patch->start1;
			$patchCopy->start2 = $patch->start2;
			$patchCopy->length1 = $patch->length1;
			$patchCopy->length2 = $patch->length2;
			$patchesCopy[$x] = $patchCopy;
		}
		return $patchesCopy;
	}

	/**
	 * Merge a set of patches onto the text.  Return a patched text, as well
	 * as a list of true/false values indicating which patches were applied.
	 * @param {Array.<patch_obj>} patches Array of patch objects.
	 * @param {string} text Old text.
	 * @return {Array.<string|Array.<boolean>>} Two element Array, containing the
	 *      new text and an array of boolean values.
	 */
	function patch_apply($patches, $text) {
		if ( count($patches) == 0) {
			return array($text,array());
		}

		// Deep copy the patches so that no changes are made to originals.
		$patches = $this->patch_deepCopy($patches);

		$nullPadding = $this->patch_addPadding($patches);
		$text = $nullPadding . $text . $nullPadding;

		$this->patch_splitMax($patches);
		// delta keeps track of the offset between the expected and actual location
		// of the previous patch.  If there are patches expected at positions 10 and
		// 20, but the first patch was found at 12, delta is 2 and the second patch
		// has an effective expected position of 22.
		$delta = 0;
		$results = array();
		for ($x = 0; $x < count($patches) ; $x++) {
			$expected_loc = $patches[$x]->start2 + $delta;
			$text1 = $this->diff_text1($patches[$x]->diffs);
			$start_loc = null;
			$end_loc = -1;
			if (mb_strlen($text1) > Match_MaxBits) {
				// patch_splitMax will only provide an oversized pattern in the case of
				// a monster delete.
				$start_loc = $this->match_main($text, mb_substr($text1, 0, Match_MaxBits ), $expected_loc);
				if ($start_loc != -1) {
					$end_loc = $this->match_main($text, mb_substr($text1,mb_strlen($text1) - Match_MaxBits), $expected_loc + mb_strlen($text1) - Match_MaxBits);
					if ($end_loc == -1 || $start_loc >= $end_loc) {
						// Can't find valid trailing context.  Drop this patch.
						$start_loc = -1;
					}
				}
			} else {
				$start_loc = $this->match_main($text, $text1, $expected_loc);
			}
			if ($start_loc == -1) {
				// No match found.  :(
				$results[$x] = false;
				// Subtract the delta for this failed patch from subsequent patches.
				$delta -= $patches[$x]->length2 - $patches[$x]->length1;
			} else {
				// Found a match.  :)
				$results[$x] = true;
				$delta = $start_loc - $expected_loc;
				$text2 = null;
				if ($end_loc == -1) {
					$text2 = mb_substr($text, $start_loc, mb_strlen($text1) );
				} else {
					$text2 = mb_substr($text, $start_loc, $end_loc + Match_MaxBits - $start_loc);
				}
				if ($text1 == $text2) {
					// Perfect match, just shove the replacement text in.
					$text = mb_substr($text, 0, $start_loc) . $this->diff_text2($patches[$x]->diffs) . mb_substr($text,$start_loc + mb_strlen($text1) );
				} else {
					// Imperfect match.  Run a diff to get a framework of equivalent
					// indices.
					$diffs = $this->diff_main($text1, $text2, false);
					if ( mb_strlen($text1) > Match_MaxBits && $this->diff_levenshtein($diffs) / mb_strlen($text1) > $this->Patch_DeleteThreshold) {
						// The end points match, but the content is unacceptably bad.
						$results[$x] = false;
					} else {
						$this->diff_cleanupSemanticLossless($diffs);
						$index1 = 0;
						$index2 = NULL;
						for ($y = 0; $y < count($patches[$x]->diffs); $y++) {
							$mod = $patches[$x]->diffs[$y];
							if ($mod[0] !== DIFF_EQUAL) {
								$index2 = $this->diff_xIndex($diffs, $index1);
							}
							if ($mod[0] === DIFF_INSERT) { // Insertion
								$text = mb_substr($text, 0, $start_loc + $index2) . $mod[1] . mb_substr($text, $start_loc + $index2);
							} elseif ($mod[0] === DIFF_DELETE) { // Deletion
								$text = mb_substr($text, 0, $start_loc + $index2) . mb_substr($text,$start_loc + $this->diff_xIndex($diffs, $index1 + mb_strlen($mod[1]) ));
							}
							if ($mod[0] !== DIFF_DELETE) {
								$index1 += mb_strlen($mod[1]);
							}
						}
					}
				}
			}
		}
		// Strip the padding off.
		$text = mb_substr($text, mb_strlen($nullPadding), mb_strlen($text) - 2*mb_strlen($nullPadding) );
		return array($text, $results);
	}

	/**
	 * Add some padding on text start and end so that edges can match something.
	 * Intended to be called only from within patch_apply.
	 * @param {Array.<patch_obj>} patches Array of patch objects.
	 * @return {string} The padding string added to each side.
	 */
	function patch_addPadding(&$patches){
		$paddingLength = $this->Patch_Margin;
		$nullPadding = '';
		for ($x = 1; $x <= $paddingLength; $x++) {
			$nullPadding .= mb_chr($x);
		}

		// Bump all the patches forward.
		for ($x = 0; $x < count($patches); $x++) {
			$patches[$x]->start1 += $paddingLength;
			$patches[$x]->start2 += $paddingLength;
		}

		// Add some padding on start of first diff.
		$patch = &$patches[0];
		$diffs = &$patch->diffs;
		if (count($diffs) == 0 || $diffs[0][0] != DIFF_EQUAL) {
			// Add nullPadding equality.
			array_unshift($diffs, array(DIFF_EQUAL, $nullPadding));
			$patch->start1 -= $paddingLength; // Should be 0.
			$patch->start2 -= $paddingLength; // Should be 0.
			$patch->length1 += $paddingLength;
			$patch->length2 += $paddingLength;
		} elseif ($paddingLength > mb_strlen($diffs[0][1]) ) {
			// Grow first equality.
			$extraLength = $paddingLength - mb_strlen($diffs[0][1]);
			$diffs[0][1] = mb_substr( $nullPadding , mb_strlen($diffs[0][1]) ) . $diffs[0][1];
			$patch->start1 -= $extraLength;
			$patch->start2 -= $extraLength;
			$patch->length1 += $extraLength;
			$patch->length2 += $extraLength;
		}

		// Add some padding on end of last diff.
		$patch = &$patches[count($patches) - 1];
		$diffs = &$patch->diffs;
		if ( count($diffs) == 0 || $diffs[ count($diffs) - 1][0] != DIFF_EQUAL) {
			// Add nullPadding equality.
			array_push($diffs, array(DIFF_EQUAL, $nullPadding) );
			$patch->length1 += $paddingLength;
			$patch->length2 += $paddingLength;
		} elseif ($paddingLength > mb_strlen( $diffs[count($diffs)-1][1] ) ) {
			// Grow last equality.
			$extraLength = $paddingLength - mb_strlen( $diffs[count($diffs)-1][1] );
			$diffs[ count($diffs)-1][1] .= mb_substr($nullPadding,0,$extraLength);
			$patch->length1 += $extraLength;
			$patch->length2 += $extraLength;
		}

		return $nullPadding;
	}

	/**
	 * Look through the patches and break up any which are longer than the maximum
	 * limit of the match algorithm.
	 * @param {Array.<patch_obj>} patches Array of patch objects.
	 */
	function patch_splitMax(&$patches) {
		for ($x = 0; $x < count($patches); $x++) {
			if ( $patches[$x]->length1 > Match_MaxBits) {
				$bigpatch = $patches[$x];
				// Remove the big old patch.
				array_splice($patches,$x--,1);
				$patch_size = Match_MaxBits;
				$start1 = $bigpatch->start1;
				$start2 = $bigpatch->start2;
				$precontext = '';
				while ( count($bigpatch->diffs) !== 0) {
					// Create one of several smaller patches.
					$patch = new patch_obj();
					$empty = true;
					$patch->start1 = $start1 - mb_strlen($precontext);
					$patch->start2 = $start2 - mb_strlen($precontext);
					if ($precontext !== '') {
						$patch->length1 = $patch->length2 = mb_strlen($precontext);
						array_push($patch->diffs, array(DIFF_EQUAL, $precontext) );
					}
					while ( count($bigpatch->diffs) !== 0 && $patch->length1 < $patch_size - $this->Patch_Margin) {
						$diff_type = $bigpatch->diffs[0][0];
						$diff_text = $bigpatch->diffs[0][1];
						if ($diff_type === DIFF_INSERT) {
							// Insertions are harmless.
							$patch->length2 += mb_strlen($diff_text);
							$start2 += mb_strlen($diff_text);
							array_push($patch->diffs, array_shift($bigpatch->diffs) );
							$empty = false;
						} else
							if ($diff_type === DIFF_DELETE && count($patch->diffs) == 1 && $patch->diffs[0][0] == DIFF_EQUAL && (mb_strlen($diff_text) > 2 * $patch_size) ) {
								// This is a large deletion.  Let it pass in one chunk.
								$patch->length1 += mb_strlen($diff_text);
								$start1 += mb_strlen($diff_text);
								$empty = false;
								array_push( $patch->diffs, array($diff_type, $diff_text) );
								array_shift($bigpatch->diffs);
							} else {
								// Deletion or equality.  Only take as much as we can stomach.
								$diff_text = mb_substr($diff_text, 0, $patch_size - $patch->length1 - $this->Patch_Margin);
								$patch->length1 += mb_strlen($diff_text);
								$start1 += mb_strlen($diff_text);
								if ($diff_type === DIFF_EQUAL) {
									$patch->length2 += mb_strlen($diff_text);
									$start2 += mb_strlen($diff_text);
								} else {
									$empty = false;
								}
								array_push($patch->diffs, array($diff_type, $diff_text) );
								if ($diff_text == $bigpatch->diffs[0][1]) {
									array_shift($bigpatch->diffs);
								} else {
									$bigpatch->diffs[0][1] = mb_substr( $bigpatch->diffs[0][1],mb_strlen($diff_text) );
								}
							}
					}
					// Compute the head context for the next patch.
					$precontext = $this->diff_text2($patch->diffs);
					$precontext = mb_substr($precontext, mb_strlen($precontext)-$this->Patch_Margin);
					// Append the end context for this patch.
					$postcontext = mb_substr( $this->diff_text1($bigpatch->diffs), 0, $this->Patch_Margin );
					if ($postcontext !== '') {
						$patch->length1 += mb_strlen($postcontext);
						$patch->length2 += mb_strlen($postcontext);
						if ( count($patch->diffs) !== 0 && $patch->diffs[ count($patch->diffs) - 1][0] === DIFF_EQUAL) {
							$patch->diffs[ count($patch->diffs) - 1][1] .= $postcontext;
						} else {
							array_push($patch->diffs, array(DIFF_EQUAL, $postcontext));
						}
					}
					if (!$empty) {
						array_splice($patches, ++$x, 0, array($patch));
					}
				}
			}
		}
	}

	/**
	 * Take a list of patches and return a textual representation.
	 * @param {Array.<patch_obj>} patches Array of patch objects.
	 * @return {string} Text representation of patches.
	 */
	function patch_toText($patches) {
		$text = array();
		for ($x = 0; $x < count($patches) ; $x++) {
			$text[$x] = $patches[$x];
		}
		return implode('',$text);
	}

	/**
	 * Parse a textual representation of patches and return a list of patch objects.
	 * @param {string} textline Text representation of patches.
	 * @return {Array.<patch_obj>} Array of patch objects.
	 * @throws {Error} If invalid input.
	 */
	function patch_fromText($textline) {
		$patches = array();
		if ($textline === '') {
			return $patches;
		}
		$text = explode("\n",$textline);
		foreach($text as $i=>$t){ if($t===''){ unset($text[$i]); } }
		$textPointer = 0;
		while ($textPointer < count($text) ) {
			$m = null;
			preg_match('/^@@ -(\d+),?(\d*) \+(\d+),?(\d*) @@$/',$text[$textPointer],$m);
			if (!$m) {
				echo_Exception('Invalid patch string: ' . $text[$textPointer]);
			}
			$patch = new patch_obj();
			array_push($patches, $patch);
			@$patch->start1 = (int)$m[1];
			if (@$m[2] === '') {
				$patch->start1--;
				$patch->length1 = 1;
			} elseif ( @$m[2] == '0') {
				$patch->length1 = 0;
			} else {
				$patch->start1--;
				@$patch->length1 = (int)$m[2];
			}

			@$patch->start2 = (int)$m[3];
			if (@$m[4] === '') {
				$patch->start2--;
				$patch->length2 = 1;
			} elseif ( @$m[4] == '0') {
				$patch->length2 = 0;
			} else {
				$patch->start2--;
				@$patch->length2 = (int)$m[4];
			}
			$textPointer++;

			while ($textPointer < count($text) ) {
				$sign = $text[$textPointer][0];
				try {
					$line = decodeURI( mb_substr($text[$textPointer],1) );
				} catch (Exception $ex) {
					// Malformed URI sequence.
					throw new Exception('Illegal escape in patch_fromText: ' . $line);
				}
				if ($sign == '-') {
					// Deletion.
					array_push( $patch->diffs, array(DIFF_DELETE, $line) );
				} elseif ($sign == '+') {
					// Insertion.
					array_push($patch->diffs, array(DIFF_INSERT, $line) );
				} elseif ($sign == ' ') {
					// Minor equality.
					array_push($patch->diffs, array(DIFF_EQUAL, $line) );
				} elseif ($sign == '@') {
					// Start of next patch.
					break;
				} elseif ($sign === '') {
					// Blank line?  Whatever.
				} else {
					// WTF?
					echo_Exception('Invalid patch mode "' . $sign . '" in: ' . $line);
				}
				$textPointer++;
			}
		}
		return $patches;
	}
}

/**
 * Class representing one patch operation.
 * @constructor
 */
class patch_obj {
	/** @type {Array.<Array.<number|string>>} */
	public $diffs = array();
	/** @type {number?} */
	public $start1 = null;
	/** @type {number?} */
	public $start2 = null;
	/** @type {number} */
	public $length1 = 0;
	/** @type {number} */
	public $length2 = 0;

	/**
	 * Emmulate GNU diff's format.
	 * Header: @@ -382,8 +481,9 @@
	 * Indicies are printed as 1-based, not 0-based.
	 * @return {string} The GNU diff string.
	 */
	function toString() {
		if ($this->length1 === 0) {
			$coords1 = $this->start1 . ',0';
		} elseif ($this->length1 == 1) {
			$coords1 = $this->start1 + 1;
		} else {
			$coords1 = ($this->start1 + 1) . ',' . $this->length1;
		}
		if ($this->length2 === 0) {
			$coords2 = $this->start2 . ',0';
		} elseif ($this->length2 == 1) {
			$coords2 = $this->start2 + 1;
		} else {
			$coords2 = ($this->start2 + 1) . ',' . $this->length2;
		}
		$text = array ( '@@ -' . $coords1 . ' +' . $coords2 . " @@\n" );

		// Escape the body of the patch with %xx notation.
		for ($x = 0; $x < count($this->diffs); $x++) {
			switch ($this->diffs[$x][0]) {
				case DIFF_INSERT :
					$op = '+';
					break;
				case DIFF_DELETE :
					$op = '-';
					break;
				case DIFF_EQUAL :
					$op = ' ';
					break;
			}
			$text[$x +1] = $op . encodeURI($this->diffs[$x][1]) . "\n";
		}
		return str_replace('%20', ' ', implode('',$text));
	}
	function __toString(){
		return $this->toString();
	}
}

define('DIFF_DELETE', -1);
define('DIFF_INSERT', 1);
define('DIFF_EQUAL', 0);

define('Match_MaxBits', PHP_INT_SIZE * 8);


function charCodeAt($str, $pos) {
	return mb_ord(mb_substr($str, $pos, 1));
}
function mb_ord($v) {
	$k = mb_convert_encoding($v, 'UCS-2LE', 'UTF-8'); 
	$k1 = ord(substr($k, 0, 1)); 
	$k2 = ord(substr($k, 1, 1)); 
	return $k2 * 256 + $k1; 
}
function mb_chr($num){
	return mb_convert_encoding('&#'.intval($num).';', 'UTF-8', 'HTML-ENTITIES');
}

/**
 * as in javascript encodeURI() following the MDN description
 *
 * @link https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/encodeURI
 * @param $url
 * @return string
 */
function encodeURI($url) {
    return strtr(rawurlencode($url), array (
        '%3B' => ';', '%2C' => ',', '%2F' => '/', '%3F' => '?', '%3A' => ':', '%40' => '@', '%26' => '&', '%3D' => '=',
        '%2B' => '+', '%24' => '$', '%21' => '!', '%2A' => '*', '%27' => '\'', '%28' => '(', '%29' => ')', '%23' => '#',
    ));
}

function decodeURI($encoded) {
	static $dontDecode;
	if (!$dontDecode) {
		$table = array (
			'%3B' => ';', '%2C' => ',', '%2F' => '/', '%3F' => '?', '%3A' => ':', '%40' => '@', '%26' => '&', '%3D' => '=',
			'%2B' => '+', '%24' => '$', '%21' => '!', '%2A' => '*', '%27' => '\'', '%28' => '(', '%29' => ')', '%23' => '#',
		);
		$dontDecode = array();
		foreach ($table as $k => $v) {
			$dontDecode[$k] = encodeURI($k);
		}
	}
	return rawurldecode(strtr($encoded, $dontDecode));
}

function echo_Exception($str){
	global $lastException;
	$lastException = $str;
	echo $str;
}
//mb_internal_encoding("UTF-8");

?>