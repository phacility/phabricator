<?php

final class DifferentialHunkParser {

  private $oldLines;
  private $newLines;
  private $intraLineDiffs;
  private $visibleLinesMask;
  private $whitespaceMode;

  /**
   * Get a map of lines on which hunks start, other than line 1. This
   * datastructure is used to determine when to render "Context not available."
   * in diffs with multiple hunks.
   *
   * @return dict<int, bool>  Map of lines where hunks start, other than line 1.
   */
  public function getHunkStartLines(array $hunks) {
    assert_instances_of($hunks, 'DifferentialHunk');

    $map = array();
    foreach ($hunks as $hunk) {
      $line = $hunk->getOldOffset();
      if ($line > 1) {
        $map[$line] = true;
      }
    }

    return $map;
  }

  private function setVisibleLinesMask($mask) {
    $this->visibleLinesMask = $mask;
    return $this;
  }
  public function getVisibleLinesMask() {
    if ($this->visibleLinesMask === null) {
      throw new Exception(
        'You must generateVisibileLinesMask before accessing this data.'
      );
    }
    return $this->visibleLinesMask;
  }

  private function setIntraLineDiffs($intra_line_diffs) {
    $this->intraLineDiffs = $intra_line_diffs;
    return $this;
  }
  public function getIntraLineDiffs() {
    if ($this->intraLineDiffs === null) {
      throw new Exception(
        'You must generateIntraLineDiffs before accessing this data.'
      );
    }
    return $this->intraLineDiffs;
  }

  private function setNewLines($new_lines) {
    $this->newLines = $new_lines;
    return $this;
  }
  public function getNewLines() {
    if ($this->newLines === null) {
      throw new Exception(
        'You must parseHunksForLineData before accessing this data.'
      );
    }
    return $this->newLines;
  }

  private function setOldLines($old_lines) {
    $this->oldLines = $old_lines;
    return $this;
  }
  public function getOldLines() {
    if ($this->oldLines === null) {
      throw new Exception(
        'You must parseHunksForLineData before accessing this data.'
      );
    }
    return $this->oldLines;
  }

  public function getOldLineTypeMap() {
    $map = array();
    $old = $this->getOldLines();
    foreach ($old as $o) {
      if (!$o) {
        continue;
      }
      $map[$o['line']] = $o['type'];
    }
    return $map;
  }

  public function setOldLineTypeMap(array $map) {
    $lines = $this->getOldLines();
    foreach ($lines as $key => $data) {
      $lines[$key]['type'] = $map[$data['line']];
    }
    $this->oldLines = $lines;
    return $this;
  }

  public function getNewLineTypeMap() {
    $map = array();
    $new = $this->getNewLines();
    foreach ($new as $n) {
      if (!$n) {
        continue;
      }
      $map[$n['line']] = $n['type'];
    }
    return $map;
  }

  public function setNewLineTypeMap(array $map) {
    $lines = $this->getNewLines();
    foreach ($lines as $key => $data) {
      $lines[$key]['type'] = $map[$data['line']];
    }
    $this->newLines = $lines;
    return $this;
  }


  public function setWhitespaceMode($white_space_mode) {
    $this->whitespaceMode = $white_space_mode;
    return $this;
  }

  private function getWhitespaceMode() {
    if ($this->whitespaceMode === null) {
      throw new Exception(
        'You must setWhitespaceMode before accessing this data.'
      );
    }
    return $this->whitespaceMode;
  }

  public function getIsDeleted() {
    foreach ($this->getNewLines() as $line) {
      if ($line) {
        // At least one new line, so the entire file wasn't deleted.
        return false;
      }
    }

    foreach ($this->getOldLines() as $line) {
      if ($line) {
        // No new lines, at least one old line; the entire file was deleted.
        return true;
      }
    }

    // This is an empty file.
    return false;
  }

  /**
   * Returns true if the hunks change any text, not just whitespace.
   */
  public function getHasTextChanges() {
    return $this->getHasChanges('text');
  }

  /**
   * Returns true if the hunks change anything, including whitespace.
   */
  public function getHasAnyChanges() {
    return $this->getHasChanges('any');
  }

  private function getHasChanges($filter) {
    if ($filter !== 'any' && $filter !== 'text') {
      throw new Exception("Unknown change filter '{$filter}'.");
    }

    $old = $this->getOldLines();
    $new = $this->getNewLines();

    $is_any = ($filter === 'any');

    foreach ($old as $key => $o) {
      $n = $new[$key];
      if ($o === null || $n === null) {
        // One side is missing, and it's impossible for both sides to be null,
        // so the other side must have something, and thus the two sides are
        // different and the file has been changed under any type of filter.
        return true;
      }

      if ($o['type'] !== $n['type']) {
        // The types are different, so either the underlying text is actually
        // different or whatever whitespace rules we're using consider them
        // different.
        return true;
      }

      if ($o['text'] !== $n['text']) {
        if ($is_any) {
          // The text is different, so there's a change.
          return true;
        } else if (trim($o['text']) !== trim($n['text'])) {
          return true;
        }
      }
    }

    // No changes anywhere in the file.
    return false;
  }


  /**
   * This function takes advantage of the parsing work done in
   * @{method:parseHunksForLineData} and continues the struggle to hammer this
   * data into something we can display to a user.
   *
   * In particular, this function re-parses the hunks to make them equivalent
   * in length for easy rendering, adding `null` as necessary to pad the
   * length.
   *
   * Anyhoo, this function is not particularly well-named but I try.
   *
   * NOTE: this function must be called after
   * @{method:parseHunksForLineData}.
   */
  public function reparseHunksForSpecialAttributes() {
    $rebuild_old = array();
    $rebuild_new = array();

    $old_lines = array_reverse($this->getOldLines());
    $new_lines = array_reverse($this->getNewLines());

    while (count($old_lines) || count($new_lines)) {
      $old_line_data = array_pop($old_lines);
      $new_line_data = array_pop($new_lines);

      if ($old_line_data) {
        $o_type = $old_line_data['type'];
      } else {
        $o_type = null;
      }

      if ($new_line_data) {
        $n_type = $new_line_data['type'];
      } else {
        $n_type = null;
      }

      // This line does not exist in the new file.
      if (($o_type != null) && ($n_type == null)) {
        $rebuild_old[] = $old_line_data;
        $rebuild_new[] = null;
        if ($new_line_data) {
          array_push($new_lines, $new_line_data);
        }
        continue;
      }

      // This line does not exist in the old file.
      if (($n_type != null) && ($o_type == null)) {
        $rebuild_old[] = null;
        $rebuild_new[] = $new_line_data;
        if ($old_line_data) {
          array_push($old_lines, $old_line_data);
        }
        continue;
      }

      $rebuild_old[] = $old_line_data;
      $rebuild_new[] = $new_line_data;
    }

    $this->setOldLines($rebuild_old);
    $this->setNewLines($rebuild_new);

    $this->updateChangeTypesForWhitespaceMode();

    return $this;
  }

  private function updateChangeTypesForWhitespaceMode() {
    $mode = $this->getWhitespaceMode();

    $mode_show_all = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;
    if ($mode === $mode_show_all) {
      // If we're showing all whitespace, we don't need to perform any updates.
      return;
    }

    $mode_trailing = DifferentialChangesetParser::WHITESPACE_IGNORE_TRAILING;
    $is_trailing = ($mode === $mode_trailing);

    $new = $this->getNewLines();
    $old = $this->getOldLines();
    foreach ($old as $key => $o) {
      $n = $new[$key];

      if (!$o || !$n) {
        continue;
      }

      if ($is_trailing) {
        // In "trailing" mode, we need to identify lines which are marked
        // changed but differ only by trailing whitespace. We mark these lines
        // unchanged.
        if ($o['type'] != $n['type']) {
          if (rtrim($o['text']) === rtrim($n['text'])) {
            $old[$key]['type'] = null;
            $new[$key]['type'] = null;
          }
        }
      } else {
        // In "ignore most" and "ignore all" modes, we need to identify lines
        // which are marked unchanged but have internal whitespace changes.
        // We want to ignore leading and trailing whitespace changes only, not
        // internal whitespace changes (`diff` doesn't have a mode for this, so
        // we have to fix it here). If the text is marked unchanged but the
        // old and new text differs by internal space, mark the lines changed.
        if ($o['type'] === null && $n['type'] === null) {
          if ($o['text'] !== $n['text']) {
            if (trim($o['text']) !== trim($n['text'])) {
              $old[$key]['type'] = '-';
              $new[$key]['type'] = '+';
            }
          }
        }
      }
    }

    $this->setOldLines($old);
    $this->setNewLines($new);

    return $this;
  }

  public function generateIntraLineDiffs() {
    $old = $this->getOldLines();
    $new = $this->getNewLines();

    $diffs = array();
    foreach ($old as $key => $o) {
      $n = $new[$key];

      if (!$o || !$n) {
        continue;
      }

      if ($o['type'] != $n['type']) {
        $diffs[$key] = ArcanistDiffUtils::generateIntralineDiff(
          $o['text'],
          $n['text']);
      }
    }

    $this->setIntraLineDiffs($diffs);

    return $this;
  }

  public function generateVisibileLinesMask() {
    $lines_context = DifferentialChangesetParser::LINES_CONTEXT;
    $old = $this->getOldLines();
    $new = $this->getNewLines();
    $max_length = max(count($old), count($new));
    $visible = false;
    $last = 0;
    $mask = array();
    for ($cursor = -$lines_context; $cursor < $max_length; $cursor++) {
      $offset = $cursor + $lines_context;
      if ((isset($old[$offset]) && $old[$offset]['type']) ||
          (isset($new[$offset]) && $new[$offset]['type'])) {
        $visible = true;
        $last = $offset;
      } else if ($cursor > $last + $lines_context) {
        $visible = false;
      }
      if ($visible && $cursor > 0) {
        $mask[$cursor] = 1;
      }
    }

    $this->setVisibleLinesMask($mask);

    return $this;
  }

  public function getOldCorpus() {
    return $this->getCorpus($this->getOldLines());
  }

  public function getNewCorpus() {
    return $this->getCorpus($this->getNewLines());
  }

  private function getCorpus(array $lines) {

    $corpus = array();
    foreach ($lines as $l) {
      if ($l['type'] != '\\') {
        if ($l['text'] === null) {
          // There's no text on this side of the diff, but insert a placeholder
          // newline so the highlighted line numbers match up.
          $corpus[] = "\n";
        } else {
          $corpus[] = $l['text'];
        }
      }
    }
    return $corpus;
  }

  public function parseHunksForLineData(array $hunks) {
    assert_instances_of($hunks, 'DifferentialHunk');

    $old_lines = array();
    $new_lines = array();
    foreach ($hunks as $hunk) {

      $lines = $hunk->getChanges();
      $lines = phutil_split_lines($lines);

      $line_type_map = array();
      foreach ($lines as $line_index => $line) {
        if (isset($line[0])) {
          $char = $line[0];
          if ($char == ' ') {
            $line_type_map[$line_index] = null;
          } else {
            $line_type_map[$line_index] = $char;
          }
        } else {
          $line_type_map[$line_index] = null;
        }
      }

      $old_line = $hunk->getOldOffset();
      $new_line = $hunk->getNewOffset();

      $num_lines = count($lines);
      for ($cursor = 0; $cursor < $num_lines; $cursor++) {
        $type = $line_type_map[$cursor];
        $data = array(
          'type'  => $type,
          'text'  => (string)substr($lines[$cursor], 1),
          'line'  => $new_line,
        );
        if ($type == '\\') {
          $type = $line_type_map[$cursor - 1];
          $data['text'] = ltrim($data['text']);
        }
        switch ($type) {
          case '+':
            $new_lines[] = $data;
            ++$new_line;
            break;
          case '-':
            $data['line'] = $old_line;
            $old_lines[] = $data;
            ++$old_line;
            break;
          default:
            $new_lines[] = $data;
            $data['line'] = $old_line;
            $old_lines[] = $data;
            ++$new_line;
            ++$old_line;
            break;
        }
      }
    }

    $this->setOldLines($old_lines);
    $this->setNewLines($new_lines);

    return $this;
  }

  public function parseHunksForHighlightMasks(
    array $changeset_hunks,
    array $old_hunks,
    array $new_hunks) {
    assert_instances_of($changeset_hunks, 'DifferentialHunk');
    assert_instances_of($old_hunks,       'DifferentialHunk');
    assert_instances_of($new_hunks,       'DifferentialHunk');

    // Put changes side by side.
    $olds = array();
    $news = array();
    foreach ($changeset_hunks as $hunk) {
      $n_old = $hunk->getOldOffset();
      $n_new = $hunk->getNewOffset();
      $changes = phutil_split_lines($hunk->getChanges());
      foreach ($changes as $line) {
        $diff_type = $line[0]; // Change type in diff of diffs.
        $orig_type = $line[1]; // Change type in the original diff.
        if ($diff_type == ' ') {
          // Use the same key for lines that are next to each other.
          $key = max(last_key($olds), last_key($news)) + 1;
          $olds[$key] = null;
          $news[$key] = null;
        } else if ($diff_type == '-') {
          $olds[] = array($n_old, $orig_type);
        } else if ($diff_type == '+') {
          $news[] = array($n_new, $orig_type);
        }
        if (($diff_type == '-' || $diff_type == ' ') && $orig_type != '-') {
          $n_old++;
        }
        if (($diff_type == '+' || $diff_type == ' ') && $orig_type != '-') {
          $n_new++;
        }
      }
    }

    $offsets_old = $this->computeOffsets($old_hunks);
    $offsets_new = $this->computeOffsets($new_hunks);

    // Highlight lines that were added on each side or removed on the other
    // side.
    $highlight_old = array();
    $highlight_new = array();
    $last = max(last_key($olds), last_key($news));
    for ($i = 0; $i <= $last; $i++) {
      if (isset($olds[$i])) {
        list($n, $type) = $olds[$i];
        if ($type == '+' ||
            ($type == ' ' && isset($news[$i]) && $news[$i][1] != ' ')) {
          $highlight_old[] = $offsets_old[$n];
        }
      }
      if (isset($news[$i])) {
        list($n, $type) = $news[$i];
        if ($type == '+' ||
            ($type == ' ' && isset($olds[$i]) && $olds[$i][1] != ' ')) {
          $highlight_new[] = $offsets_new[$n];
        }
      }
    }

    return array($highlight_old, $highlight_new);
  }

  public function makeContextDiff(
    array $hunks,
    PhabricatorInlineCommentInterface $inline,
    $add_context) {

    assert_instances_of($hunks, 'DifferentialHunk');

    $context = array();
    $debug = false;
    if ($debug) {
      $context[] = 'Inline: '.$inline->getIsNewFile().' '.
        $inline->getLineNumber().' '.$inline->getLineLength();
      foreach ($hunks as $hunk) {
        $context[] = 'hunk: '.$hunk->getOldOffset().'-'.
          $hunk->getOldLen().'; '.$hunk->getNewOffset().'-'.$hunk->getNewLen();
        $context[] = $hunk->getChanges();
      }
    }

    if ($inline->getIsNewFile()) {
      $prefix = '+';
    } else {
      $prefix = '-';
    }
    foreach ($hunks as $hunk) {
      if ($inline->getIsNewFile()) {
        $offset = $hunk->getNewOffset();
        $length = $hunk->getNewLen();
      } else {
        $offset = $hunk->getOldOffset();
        $length = $hunk->getOldLen();
      }
      $start = $inline->getLineNumber() - $offset;
      $end = $start + $inline->getLineLength();
      // We need to go in if $start == $length, because the last line
      // might be a "\No newline at end of file" marker, which we want
      // to show if the additional context is > 0.
      if ($start <= $length && $end >= 0) {
        $start = $start - $add_context;
        $end = $end + $add_context;
        $hunk_content = array();
        $hunk_pos = array( "-" => 0, "+" => 0 );
        $hunk_offset = array( "-" => NULL, "+" => NULL );
        $hunk_last = array( "-" => NULL, "+" => NULL );
        foreach (explode("\n", $hunk->getChanges()) as $line) {
          $in_common = strncmp($line, " ", 1) === 0;
          $in_old = strncmp($line, "-", 1) === 0 || $in_common;
          $in_new = strncmp($line, "+", 1) === 0 || $in_common;
          $in_selected = strncmp($line, $prefix, 1) === 0;
          $skip = !$in_selected && !$in_common;
          if ($hunk_pos[$prefix] <= $end) {
            if ($start <= $hunk_pos[$prefix]) {
              if (!$skip || ($hunk_pos[$prefix] != $start &&
                $hunk_pos[$prefix] != $end)) {
                  if ($in_old) {
                    if ($hunk_offset["-"] === NULL) {
                      $hunk_offset["-"] = $hunk_pos["-"];
                    }
                    $hunk_last["-"] = $hunk_pos["-"];
                  }
                  if ($in_new) {
                    if ($hunk_offset["+"] === NULL) {
                      $hunk_offset["+"] = $hunk_pos["+"];
                    }
                    $hunk_last["+"] = $hunk_pos["+"];
                  }

                  $hunk_content[] = $line;
                }
            }
            if ($in_old) { ++$hunk_pos["-"]; }
            if ($in_new) { ++$hunk_pos["+"]; }
          }
        }
        if ($hunk_offset["-"] !== NULL || $hunk_offset["+"] !== NULL) {
          $header = "@@";
          if ($hunk_offset["-"] !== NULL) {
            $header .= " -" . ($hunk->getOldOffset() + $hunk_offset["-"]) .
              "," . ($hunk_last["-"] - $hunk_offset["-"] + 1);
          }
          if ($hunk_offset["+"] !== NULL) {
            $header .= " +" . ($hunk->getNewOffset() + $hunk_offset["+"]) .
              "," . ($hunk_last["+"] - $hunk_offset["+"] + 1);
          }
          $header .= " @@";
          $context[] = $header;
          $context[] = implode("\n", $hunk_content);
        }
      }
    }
    return implode("\n", $context);
  }

  private function computeOffsets(array $hunks) {
    assert_instances_of($hunks, 'DifferentialHunk');

    $offsets = array();
    $n = 1;
    foreach ($hunks as $hunk) {
      for ($i = 0; $i < $hunk->getNewLen(); $i++) {
        $offsets[$n] = $hunk->getNewOffset() + $i;
        $n++;
      }
    }
    return $offsets;
  }
}
