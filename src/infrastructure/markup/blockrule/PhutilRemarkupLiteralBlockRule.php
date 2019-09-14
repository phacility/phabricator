<?php

final class PhutilRemarkupLiteralBlockRule extends PhutilRemarkupBlockRule {

  public function getPriority() {
    return 450;
  }

  public function getMatchingLineCount(array $lines, $cursor) {
    // NOTE: We're consuming all continguous blocks of %%% literals, so this:
    //
    //    %%%a%%%
    //    %%%b%%%
    //
    // ...is equivalent to:
    //
    //    %%%a
    //    b%%%
    //
    // If they are separated by a blank newline, they are parsed as two
    // different blocks. This more clearly represents the original text in the
    // output text and assists automated escaping of blocks coming into the
    // system.

    $num_lines = 0;
    while (preg_match('/^\s*%%%/', $lines[$cursor])) {
      $num_lines++;

      // If the line has ONLY "%%%", the block opener doesn't get to double
      // up as a block terminator.
      if (preg_match('/^\s*%%%\s*\z/', $lines[$cursor])) {
        $num_lines++;
        $cursor++;
      }

      while (isset($lines[$cursor])) {
        if (!preg_match('/%%%\s*$/', $lines[$cursor])) {
          $num_lines++;
          $cursor++;
          continue;
        }
        break;
      }

      $cursor++;

      $found_empty = false;
      while (isset($lines[$cursor])) {
        if (!strlen(trim($lines[$cursor]))) {
          $num_lines++;
          $cursor++;
          $found_empty = true;
          continue;
        }
        break;
      }

      if ($found_empty) {
        // If there's an empty line after the block, stop merging blocks.
        break;
      }

      if (!isset($lines[$cursor])) {
        // If we're at the end of the input, stop looking for more lines.
        break;
      }
    }

    return $num_lines;
  }

  public function markupText($text, $children) {
    $text = rtrim($text);
    $text = phutil_split_lines($text, $retain_endings = true);
    foreach ($text as $key => $line) {
      $line = preg_replace('/^\s*%%%/', '', $line);
      $line = preg_replace('/%%%(\s*)\z/', '\1', $line);
      $text[$key] = $line;
    }

    if ($this->getEngine()->isTextMode()) {
      return implode('', $text);
    }

    return phutil_tag(
      'p',
      array(
        'class' => 'remarkup-literal',
      ),
      phutil_implode_html(phutil_tag('br', array()), $text));
  }

}
