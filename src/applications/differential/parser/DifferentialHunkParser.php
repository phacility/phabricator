<?php

final class DifferentialHunkParser {

  private $isUnchanged;
  private $hasWhiteLines;
  private $isDeleted;
  private $oldLines;
  private $newLines;
  private $oldLineMarkerMap;
  private $newLineMarkerMap;
  private $skipIntraLines;
  private $whitespaceMode;
  private $intraLineDiffs;
  private $visibleLinesMask;

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

  private function setSkipIntraLines($skip_intra_lines) {
    $this->skipIntraLines = $skip_intra_lines;
    return $this;
  }
  public function getSkipIntraLines() {
    if ($this->skipIntraLines === null) {
      throw new Exception(
        'You must reparseHunksForSpecialAttributes before accessing this data.'
      );
    }
    return $this->skipIntraLines;
  }

  private function setNewLineMarkerMap($new_line_marker_map) {
    $this->newLineMarkerMap = $new_line_marker_map;
    return $this;
  }
  public function getNewLineMarkerMap() {
    if ($this->newLineMarkerMap === null) {
      throw new Exception(
        'You must parseHunksForLineData before accessing this data.'
      );
    }
    return $this->newLineMarkerMap;
  }

  private function setOldLineMarkerMap($old_line_marker_map) {
    $this->oldLineMarkerMap = $old_line_marker_map;
    return $this;
  }
  public function getOldLineMarkerMap() {
    if ($this->oldLineMarkerMap === null) {
      throw new Exception(
        'You must parseHunksForLineData before accessing this data.'
      );
    }
    return $this->oldLineMarkerMap;
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

  private function setIsDeleted($is_deleted) {
    $this->isDeleted = $is_deleted;
    return $this;
  }
  public function getIsDeleted() {
    return $this->isDeleted;
  }

  private function setHasWhiteLines($has_white_lines) {
    $this->hasWhiteLines = $has_white_lines;
    return $this;
  }
  public function getHasWhiteLines() {
    return $this->hasWhiteLines;
  }

  public function setIsUnchanged($is_unchanged) {
    $this->isUnchanged = $is_unchanged;
    return $this;
  }
  public function getIsUnchanged() {
    return $this->isUnchanged;
  }

  /**
   * This function takes advantage of the parsing work done in
   * @{method:parseHunksForLineData} and continues the struggle to hammer this
   * data into something we can display to a user.
   *
   * In particular, this function re-parses the hunks to make them equivalent
   * in length for easy rendering, adding `null` as necessary to pad the
   * length. Further, this re-parsing stage figures out various special
   * properties about the changes such as if the change is a delete, has any
   * whitelines, or has any changes whatsoever. Finally, this function
   * calculates what lines - if any - should be skipped within a diff display,
   * ostensibly because they don't have anything to do with the current set
   * of changes with respect to display options.
   *
   * Anyhoo, this function is not particularly well-named but I try.
   *
   * NOTE: this function must be called after
   * @{method:parseHunksForLineData}.
   * NOTE: you must @{method:setWhitespaceMode} before calling this method.
   */
  public function reparseHunksForSpecialAttributes() {
    $rebuild_old = array();
    $rebuild_new = array();
    $skip_intra = array();

    $old_lines = array_reverse($this->getOldLines());
    $new_lines = array_reverse($this->getNewLines());

    $whitelines = false;
    $changed = false;

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

      if (($o_type != null) && ($n_type == null)) {
        $rebuild_old[] = $old_line_data;
        $rebuild_new[] = null;
        if ($new_line_data) {
          array_push($new_lines, $new_line_data);
        }
        $changed = true;
        continue;
      }

      if (($n_type != null) && ($o_type == null)) {
        $rebuild_old[] = null;
        $rebuild_new[] = $new_line_data;
        if ($old_line_data) {
          array_push($old_lines, $old_line_data);
        }
        $changed = true;
        continue;
      }

      if ($this->getWhitespaceMode() !=
          DifferentialChangesetParser::WHITESPACE_SHOW_ALL) {
        $similar = false;
        switch ($this->getWhitespaceMode()) {
          case DifferentialChangesetParser::WHITESPACE_IGNORE_TRAILING:
            if (rtrim($old_line_data['text']) ==
                rtrim($new_line_data['text'])) {
              if ($old_line_data['type']) {
                // If we're converting this into an unchanged line because of
                // a trailing whitespace difference, mark it as a whitespace
                // change so we can show "This file was modified only by
                // adding or removing trailing whitespace." instead of
                // "This file was not modified.".
                $whitelines = true;
              }
              $similar = true;
            }
            break;
          default:
            // In this case, the lines are similar if there is no change type
            // (that is, just trust the diff algorithm).
            if (!$old_line_data['type']) {
              $similar = true;
            }
            break;
        }
        if ($similar) {
          if ($old_line_data['type'] == '\\') {
            // These are similar because they're "No newline at end of file"
            // comments.
          } else {
            $old_line_data['type'] = null;
            $new_line_data['type'] = null;
            $skip_intra[count($rebuild_old)] = true;
          }
        } else {
          $changed = true;
        }
      } else {
        $changed = true;
      }

      $rebuild_old[] = $old_line_data;
      $rebuild_new[] = $new_line_data;
    }

    $this->setOldLines($rebuild_old);
    $this->setNewLines($rebuild_new);

    $this->setIsUnchanged(!$changed);
    $this->setHasWhiteLines($whitelines);
    $this->setIsDeleted(array_filter($this->getOldLines()) &&
                        !array_filter($this->getNewLines()));
    $this->setSkipIntraLines($skip_intra);

    return $this;
  }

  public function updateParsedHunksText($old_text, $new_text) {
    if ($old_text || $new_text) {

      // Use this parser's side-by-side line information -- notably, the
      // change types -- but replace all the line text.
      // This lets us render whitespace-only changes without marking them as
      // different.

      $old = $this->getOldLines();
      $new = $this->getNewLines();

      foreach ($old as $k => $desc) {
        if (empty($desc)) {
          continue;
        }
        $old[$k]['text'] = idx($old_text, $desc['line']);
      }
      $skip_intra = $this->getSkipIntraLines();
      foreach ($new as $k => $desc) {
        if (empty($desc)) {
          continue;
        }
        $new[$k]['text'] = idx($new_text, $desc['line']);

        if ($this->whitespaceMode ==
            DifferentialChangesetParser::WHITESPACE_IGNORE_FORCE) {
          // Under forced ignore mode, ignore even internal whitespace
          // changes.
          continue;
        }

        // If there's a corresponding "old" text and the line is marked as
        // unchanged, test if there are internal whitespace changes between
        // non-whitespace characters, e.g. spaces added to a string or spaces
        // added around operators. If we find internal spaces, mark the line
        // as changed.
        //
        // We only need to do this for "new" lines because any line that is
        // missing either "old" or "new" text certainly can not have internal
        // whitespace changes without also having non-whitespace changes,
        // because characters had to be either added or removed to create the
        // possibility of internal whitespace.
        if (isset($old[$k]['text']) && empty($new[$k]['type'])) {
          if (trim($old[$k]['text']) != trim($new[$k]['text'])) {
            // The strings aren't the same when trimmed, so there are internal
            // whitespace changes. Mark this line changed.
            $old[$k]['type'] = '-';
            $new[$k]['type'] = '+';

            // Re-mark this line for intraline diffing.
            unset($skip_intra[$k]);
          }
        }
      }

      $this->setSkipIntraLines($skip_intra);
      $this->setOldLines($old);
      $this->setNewLines($new);
    }

    return $this;
  }

  public function generateIntraLineDiffs() {
    $old = $this->getOldLines();
    $new = $this->getNewLines();
    $skip_intra = $this->getSkipIntraLines();
    $intra_line_diffs = array();

    $min_length = min(count($old), count($new));
    for ($ii = 0; $ii < $min_length; $ii++) {
      if ($old[$ii] || $new[$ii]) {
        if (isset($old[$ii]['text'])) {
          $otext = $old[$ii]['text'];
        } else {
          $otext = '';
        }
        if (isset($new[$ii]['text'])) {
          $ntext = $new[$ii]['text'];
        } else {
          $ntext = '';
        }
        if ($otext != $ntext && empty($skip_intra[$ii])) {
          $intra_line_diffs[$ii] = ArcanistDiffUtils::generateIntralineDiff(
            $otext,
            $ntext);
        }
      }
    }

    $this->setIntraLineDiffs($intra_line_diffs);

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
    $old_line_marker_map = array();
    $new_line_marker_map = array();

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
      if ($old_line > 1) {
        $old_line_marker_map[] = $old_line;
      } else if ($new_line > 1) {
        $new_line_marker_map[] = $new_line;
      }

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
    $this->setOldLineMarkerMap($old_line_marker_map);
    $this->setNewLineMarkerMap($new_line_marker_map);

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
