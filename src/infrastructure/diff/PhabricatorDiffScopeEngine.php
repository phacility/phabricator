<?php

final class PhabricatorDiffScopeEngine
  extends Phobject {

  private $lineTextMap;
  private $lineDepthMap;

  public function setLineTextMap(array $map) {
    if (array_key_exists(0, $map)) {
      throw new Exception(
        pht('ScopeEngine text map must be a 1-based map of lines.'));
    }

    $expect = 1;
    foreach ($map as $key => $value) {
      if ($key === $expect) {
        $expect++;
        continue;
      }

      throw new Exception(
        pht(
          'ScopeEngine text map must be a contiguous map of '.
          'lines, but is not: found key "%s" where key "%s" was expected.',
          $key,
          $expect));
    }

    $this->lineTextMap = $map;

    return $this;
  }

  public function getLineTextMap() {
    if ($this->lineTextMap === null) {
      throw new PhutilInvalidStateException('setLineTextMap');
    }
    return $this->lineTextMap;
  }

  public function getScopeStart($line) {
    $text_map = $this->getLineTextMap();
    $depth_map = $this->getLineDepthMap();
    $length = count($text_map);

    // Figure out the effective depth of the line we're getting scope for.
    // If the line is just whitespace, it may have no depth on its own. In
    // this case, we look for the next line.
    $line_depth = null;
    for ($ii = $line; $ii <= $length; $ii++) {
      if ($depth_map[$ii] !== null) {
        $line_depth = $depth_map[$ii];
        break;
      }
    }

    // If we can't find a line depth for the target line, just bail.
    if ($line_depth === null) {
      return null;
    }

    // Limit the maximum number of lines we'll examine. If a user has a
    // million-line diff of nonsense, scanning the whole thing is a waste
    // of time.
    $search_range = 1000;
    $search_until = max(0, $ii - $search_range);

    for ($ii = $line - 1; $ii > $search_until; $ii--) {
      $line_text = $text_map[$ii];

      // This line is in missing context: the diff was diffed with partial
      // context, and we ran out of context before finding a good scope line.
      // Bail out, we don't want to jump across missing context blocks.
      if ($line_text === null) {
        return null;
      }

      $depth = $depth_map[$ii];

      // This line is all whitespace. This isn't a possible match.
      if ($depth === null) {
        continue;
      }

      // Don't match context lines which are too deeply indented, since they
      // are very unlikely to be symbol definitions.
      if ($depth > 2) {
        continue;
      }

      // The depth is the same as (or greater than) the depth we started with,
      // so this isn't a possible match.
      if ($depth >= $line_depth) {
        continue;
      }

      // Reject lines which begin with "}" or "{". These lines are probably
      // never good matches.
      if (preg_match('/^\s*[{}]/i', $line_text)) {
        continue;
      }

      return $ii;
    }

    return null;
  }

  private function getLineDepthMap() {
    if (!$this->lineDepthMap) {
      $this->lineDepthMap = $this->newLineDepthMap();
    }

    return $this->lineDepthMap;
  }

  private function getTabWidth() {
    // TODO: This should be configurable once we handle tab widths better.
    return 2;
  }

  private function newLineDepthMap() {
    $text_map = $this->getLineTextMap();
    $tab_width = $this->getTabWidth();

    $depth_map = array();
    foreach ($text_map as $line_number => $line_text) {
      if ($line_text === null) {
        $depth_map[$line_number] = null;
        continue;
      }

      $len = strlen($line_text);

      // If the line has no actual text, don't assign it a depth.
      if (!$len || !strlen(trim($line_text))) {
        $depth_map[$line_number] = null;
        continue;
      }

      $count = 0;
      for ($ii = 0; $ii < $len; $ii++) {
        $c = $line_text[$ii];
        if ($c == ' ') {
          $count++;
        } else if ($c == "\t") {
          $count += $tab_width;
        } else {
          break;
        }
      }

      // Round down to cheat our way through the " *" parts of docblock
      // comments.
      $depth = (int)floor($count / $tab_width);

      $depth_map[$line_number] = $depth;
    }

    return $depth_map;
  }

}
