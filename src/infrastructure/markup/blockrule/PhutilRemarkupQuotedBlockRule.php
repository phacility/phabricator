<?php

abstract class PhutilRemarkupQuotedBlockRule
  extends PhutilRemarkupBlockRule {

  final public function supportsChildBlocks() {
    return true;
  }

  final protected function normalizeQuotedBody($text) {
    $text = phutil_split_lines($text, true);
    foreach ($text as $key => $line) {
      $text[$key] = substr($line, 1);
    }

    // If every line in the block is empty or begins with at least one leading
    // space, strip the initial space off each line. When we quote text, we
    // normally add "> " (with a space) to the beginning of each line, which
    // can disrupt some other rules. If the block appears to have this space
    // in front of each line, remove it.

    $strip_space = true;
    foreach ($text as $key => $line) {
      $len = strlen($line);

      if (!$len) {
        // We'll still strip spaces if there are some completely empty
        // lines, they may have just had trailing whitespace trimmed.
        continue;
      }

      // If this line is part of a nested quote block, just ignore it when
      // realigning this quote block. It's either an author attribution
      // line with ">>!", or we'll deal with it in a subrule when processing
      // the nested quote block.
      if ($line[0] == '>') {
        continue;
      }

      if ($line[0] == ' ' || $line[0] == "\n") {
        continue;
      }

      // The first character of this line is something other than a space, so
      // we can't strip spaces.
      $strip_space = false;
      break;
    }

    if ($strip_space) {
      foreach ($text as $key => $line) {
        $len = strlen($line);
        if (!$len) {
          continue;
        }

        if ($line[0] !== ' ') {
          continue;
        }

        $text[$key] = substr($line, 1);
      }
    }

    // Strip leading empty lines.
    foreach ($text as $key => $line) {
      if (!strlen(trim($line))) {
        unset($text[$key]);
      }
    }

    return implode('', $text);
  }

  final protected function getQuotedText($text) {
    $text = rtrim($text, "\n");

    $no_whitespace = array(
      // For readability, we render nested quotes as ">> quack",
      // not "> > quack".
      '>' => true,

      // If the line is empty except for a newline, do not add an
      // unnecessary dangling space.
      "\n" => true,
    );

    $text = phutil_split_lines($text, true);
    foreach ($text as $key => $line) {
      $c = null;
      if (isset($line[0])) {
        $c = $line[0];
      } else {
        $c = null;
      }

      if (isset($no_whitespace[$c])) {
        $text[$key] = '>'.$line;
      } else {
        $text[$key] = '> '.$line;
      }
    }
    $text = implode('', $text);

    return $text;
  }

}
