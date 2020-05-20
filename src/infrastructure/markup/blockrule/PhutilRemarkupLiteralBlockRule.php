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

    $start_pattern = '(^\s*%%%)';
    $end_pattern = '(%%%\s*$)';
    $trivial_pattern = '(^\s*%%%\s*$)';

    if (!preg_match($start_pattern, $lines[$cursor])) {
      return 0;
    }

    $start_cursor = $cursor;

    $found_empty = false;
    $block_start = null;
    while (true) {
      if (!isset($lines[$cursor])) {
        break;
      }

      $line = $lines[$cursor];

      if ($block_start === null) {
        $is_start = preg_match($start_pattern, $line);

        // If we've matched a block and then consumed one or more empty lines
        // after it, stop merging more blocks into the match.
        if ($found_empty) {
          break;
        }

        if ($is_start) {
          $block_start = $cursor;
        }
      }

      if ($block_start !== null) {
        $is_end = preg_match($end_pattern, $line);

        // If a line contains only "%%%", it will match both the start and
        // end patterns, but it only counts as a block start.
        if ($is_end && ($cursor === $block_start)) {
          $is_trivial = preg_match($trivial_pattern, $line);
          if ($is_trivial) {
            $is_end = false;
          }
        }

        if ($is_end) {
          $block_start = null;
          $cursor++;
          continue;
        }
      }

      if ($block_start === null) {
        if (strlen(trim($line))) {
          break;
        }
        $found_empty = true;
      }

      $cursor++;
    }

    return ($cursor - $start_cursor);
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
