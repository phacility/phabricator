<?php

final class DifferentialHunkParser extends Phobject {

  private $oldLines;
  private $newLines;
  private $intraLineDiffs;
  private $depthOnlyLines;
  private $visibleLinesMask;
  private $normalized;

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
      throw new PhutilInvalidStateException('generateVisibleLinesMask');
    }
    return $this->visibleLinesMask;
  }

  private function setIntraLineDiffs($intra_line_diffs) {
    $this->intraLineDiffs = $intra_line_diffs;
    return $this;
  }
  public function getIntraLineDiffs() {
    if ($this->intraLineDiffs === null) {
      throw new PhutilInvalidStateException('generateIntraLineDiffs');
    }
    return $this->intraLineDiffs;
  }

  private function setNewLines($new_lines) {
    $this->newLines = $new_lines;
    return $this;
  }
  public function getNewLines() {
    if ($this->newLines === null) {
      throw new PhutilInvalidStateException('parseHunksForLineData');
    }
    return $this->newLines;
  }

  private function setOldLines($old_lines) {
    $this->oldLines = $old_lines;
    return $this;
  }
  public function getOldLines() {
    if ($this->oldLines === null) {
      throw new PhutilInvalidStateException('parseHunksForLineData');
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
      $lines[$key]['type'] = idx($map, $data['line']);
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
      $lines[$key]['type'] = idx($map, $data['line']);
    }
    $this->newLines = $lines;
    return $this;
  }

  public function setDepthOnlyLines(array $map) {
    $this->depthOnlyLines = $map;
    return $this;
  }

  public function getDepthOnlyLines() {
    return $this->depthOnlyLines;
  }

  public function setNormalized($normalized) {
    $this->normalized = $normalized;
    return $this;
  }

  public function getNormalized() {
    return $this->normalized;
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
   * Returns true if the hunks change anything, including whitespace.
   */
  public function getHasAnyChanges() {
    return $this->getHasChanges('any');
  }

  private function getHasChanges($filter) {
    if ($filter !== 'any' && $filter !== 'text') {
      throw new Exception(pht("Unknown change filter '%s'.", $filter));
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

    $this->updateChangeTypesForNormalization();

    return $this;
  }

  public function generateIntraLineDiffs() {
    $old = $this->getOldLines();
    $new = $this->getNewLines();

    $diffs = array();
    $depth_only = array();
    foreach ($old as $key => $o) {
      $n = $new[$key];

      if (!$o || !$n) {
        continue;
      }

      if ($o['type'] != $n['type']) {
        $o_segments = array();
        $n_segments = array();
        $tab_width = 2;

        $o_text = $o['text'];
        $n_text = $n['text'];

        if ($o_text !== $n_text && (ltrim($o_text) === ltrim($n_text))) {
          $o_depth = $this->getIndentDepth($o_text, $tab_width);
          $n_depth = $this->getIndentDepth($n_text, $tab_width);

          if ($o_depth < $n_depth) {
            $segment_type = '>';
            $segment_width = $this->getCharacterCountForVisualWhitespace(
              $n_text,
              ($n_depth - $o_depth),
              $tab_width);
            if ($segment_width) {
              $n_text = substr($n_text, $segment_width);
              $n_segments[] = array(
                $segment_type,
                $segment_width,
              );
            }
          } else if ($o_depth > $n_depth) {
            $segment_type = '<';
            $segment_width = $this->getCharacterCountForVisualWhitespace(
              $o_text,
              ($o_depth - $n_depth),
              $tab_width);
            if ($segment_width) {
              $o_text = substr($o_text, $segment_width);
              $o_segments[] = array(
                $segment_type,
                $segment_width,
              );
            }
          }

          // If there are no remaining changes to this line after we've marked
          // off the indent depth changes, this line was only modified by
          // changing the indent depth. Mark it for later so we can change how
          // it is displayed.
          if ($o_text === $n_text) {
            $depth_only[$key] = $segment_type;
          }
        }

        $intraline_segments = ArcanistDiffUtils::generateIntralineDiff(
          $o_text,
          $n_text);

        foreach ($intraline_segments[0] as $o_segment) {
          $o_segments[] = $o_segment;
        }

        foreach ($intraline_segments[1] as $n_segment) {
          $n_segments[] = $n_segment;
        }

        $diffs[$key] = array(
          $o_segments,
          $n_segments,
        );
      }
    }

    $this->setIntraLineDiffs($diffs);
    $this->setDepthOnlyLines($depth_only);

    return $this;
  }

  public function generateVisibleBlocksMask($lines_context) {

    // See T13468. This is similar to "generateVisibleLinesMask()", but
    // attempts to work around a series of bugs which cancel each other
    // out but make a mess of the intermediate steps.

    $old = $this->getOldLines();
    $new = $this->getNewLines();

    $length = max(count($old), count($new));

    $visible_lines = array();
    for ($ii = 0; $ii < $length; $ii++) {
      $old_visible = (isset($old[$ii]) && $old[$ii]['type']);
      $new_visible = (isset($new[$ii]) && $new[$ii]['type']);

      $visible_lines[$ii] = ($old_visible || $new_visible);
    }

    $mask = array();
    $reveal_cursor = -1;
    for ($ii = 0; $ii < $length; $ii++) {

      // If this line isn't visible, it isn't going to reveal anything.
      if (!$visible_lines[$ii]) {

        // If it hasn't been revealed by a nearby line, mark it as masked.
        if (empty($mask[$ii])) {
          $mask[$ii] = false;
        }

        continue;
      }

      // If this line is visible, reveal all the lines nearby.

      // First, compute the minimum and maximum offsets we want to reveal.
      $min_reveal = max($ii - $lines_context, 0);
      $max_reveal = min($ii + $lines_context, $length - 1);

      // Naively, we'd do more work than necessary when revealing context for
      // several adjacent visible lines: we would mark all the overlapping
      // lines as revealed several times.

      // To avoid duplicating work, keep track of the largest line we've
      // revealed to. Since we reveal context by marking every consecutive
      // line, we don't need to touch any line above it.
      $min_reveal = max($min_reveal, $reveal_cursor);

      // Reveal the remaining unrevealed lines.
      for ($jj = $min_reveal; $jj <= $max_reveal; $jj++) {
        $mask[$jj] = true;
      }

      // Move the cursor to the next line which may still need to be revealed.
      $reveal_cursor = $max_reveal + 1;
    }

    $this->setVisibleLinesMask($mask);

    return $mask;
  }

  public function generateVisibleLinesMask($lines_context) {
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
      if ($l === null) {
        $corpus[] = "\n";
        continue;
      }

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
      $lines = $hunk->getSplitLines();

      $line_type_map = array();
      $line_text = array();
      foreach ($lines as $line_index => $line) {
        if (isset($line[0])) {
          $char = $line[0];
          switch ($char) {
            case ' ':
              $line_type_map[$line_index] = null;
              $line_text[$line_index] = substr($line, 1);
              break;
            case "\r":
            case "\n":
              // NOTE: Normally, the first character is a space, plus, minus or
              // backslash, but it may be a newline if it used to be a space and
              // trailing whitespace has been stripped via email transmission or
              // some similar mechanism. In these cases, we essentially pretend
              // the missing space is still there.
              $line_type_map[$line_index] = null;
              $line_text[$line_index] = $line;
              break;
            case '+':
            case '-':
            case '\\':
              $line_type_map[$line_index] = $char;
              $line_text[$line_index] = substr($line, 1);
              break;
            default:
              throw new Exception(
                pht(
                  'Unexpected leading character "%s" at line index %s!',
                  $char,
                  $line_index));
          }
        } else {
          $line_type_map[$line_index] = null;
          $line_text[$line_index] = '';
        }
      }

      $old_line = $hunk->getOldOffset();
      $new_line = $hunk->getNewOffset();

      $num_lines = count($lines);
      for ($cursor = 0; $cursor < $num_lines; $cursor++) {
        $type = $line_type_map[$cursor];
        $data = array(
          'type'  => $type,
          'text'  => $line_text[$cursor],
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
    $olds_cursor = -1;
    $news_cursor = -1;
    foreach ($changeset_hunks as $hunk) {
      $n_old = $hunk->getOldOffset();
      $n_new = $hunk->getNewOffset();
      $changes = $hunk->getSplitLines();
      foreach ($changes as $line) {
        $diff_type = $line[0]; // Change type in diff of diffs.
        $is_same = ($diff_type === ' ');
        $is_add = ($diff_type === '+');
        $is_rem = ($diff_type === '-');

        $orig_type = $line[1]; // Change type in the original diff.

        if ($is_same) {
          // Use the same key for lines that are next to each other.
          if ($olds_cursor > $news_cursor) {
            $key = $olds_cursor + 1;
          } else {
            $key = $news_cursor + 1;
          }
          $olds[$key] = null;
          $news[$key] = null;
          $olds_cursor = $key;
          $news_cursor = $key;
        } else if ($is_rem) {
          $olds[] = array($n_old, $orig_type);
          $olds_cursor++;
        } else if ($is_add) {
          $news[] = array($n_new, $orig_type);
          $news_cursor++;
        } else {
          throw new Exception(
            pht(
              'Found unknown intradiff source line, expected a line '.
              'beginning with "+", "-", or " " (space): %s.',
              $line));
        }

        // See T13539. Don't increment the line count if this line was removed,
        // or if the line is a "No newline at end of file" marker.
        $not_a_line = ($orig_type === '-' || $orig_type === '\\');
        if ($not_a_line) {
          continue;
        }

        if ($is_same || $is_rem) {
          $n_old++;
        }

        if ($is_same || $is_add) {
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
          if (isset($offsets_old[$n])) {
            $highlight_old[] = $offsets_old[$n];
          }
        }
      }
      if (isset($news[$i])) {
        list($n, $type) = $news[$i];
        if ($type == '+' ||
            ($type == ' ' && isset($olds[$i]) && $olds[$i][1] != ' ')) {
          if (isset($offsets_new[$n])) {
            $highlight_new[] = $offsets_new[$n];
          }
        }
      }
    }

    return array($highlight_old, $highlight_new);
  }

  public function makeContextDiff(
    array $hunks,
    $is_new,
    $line_number,
    $line_length,
    $add_context) {

    assert_instances_of($hunks, 'DifferentialHunk');

    $context = array();

    if ($is_new) {
      $prefix = '+';
    } else {
      $prefix = '-';
    }

    foreach ($hunks as $hunk) {
      if ($is_new) {
        $offset = $hunk->getNewOffset();
        $length = $hunk->getNewLen();
      } else {
        $offset = $hunk->getOldOffset();
        $length = $hunk->getOldLen();
      }
      $start = $line_number - $offset;
      $end = $start + $line_length;
      // We need to go in if $start == $length, because the last line
      // might be a "\No newline at end of file" marker, which we want
      // to show if the additional context is > 0.
      if ($start <= $length && $end >= 0) {
        $start = $start - $add_context;
        $end = $end + $add_context;
        $hunk_content = array();
        $hunk_pos = array('-' => 0, '+' => 0);
        $hunk_offset = array('-' => null, '+' => null);
        $hunk_last = array('-' => null, '+' => null);
        foreach (explode("\n", $hunk->getChanges()) as $line) {
          $in_common = strncmp($line, ' ', 1) === 0;
          $in_old = strncmp($line, '-', 1) === 0 || $in_common;
          $in_new = strncmp($line, '+', 1) === 0 || $in_common;
          $in_selected = strncmp($line, $prefix, 1) === 0;
          $skip = !$in_selected && !$in_common;
          if ($hunk_pos[$prefix] <= $end) {
            if ($start <= $hunk_pos[$prefix]) {
              if (!$skip || ($hunk_pos[$prefix] != $start &&
                $hunk_pos[$prefix] != $end)) {
                  if ($in_old) {
                    if ($hunk_offset['-'] === null) {
                      $hunk_offset['-'] = $hunk_pos['-'];
                    }
                    $hunk_last['-'] = $hunk_pos['-'];
                  }
                  if ($in_new) {
                    if ($hunk_offset['+'] === null) {
                      $hunk_offset['+'] = $hunk_pos['+'];
                    }
                    $hunk_last['+'] = $hunk_pos['+'];
                  }

                  $hunk_content[] = $line;
                }
            }
            if ($in_old) { ++$hunk_pos['-']; }
            if ($in_new) { ++$hunk_pos['+']; }
          }
        }
        if ($hunk_offset['-'] !== null || $hunk_offset['+'] !== null) {
          $header = '@@';
          if ($hunk_offset['-'] !== null) {
            $header .= ' -'.($hunk->getOldOffset() + $hunk_offset['-']).
              ','.($hunk_last['-'] - $hunk_offset['-'] + 1);
          }
          if ($hunk_offset['+'] !== null) {
            $header .= ' +'.($hunk->getNewOffset() + $hunk_offset['+']).
              ','.($hunk_last['+'] - $hunk_offset['+'] + 1);
          }
          $header .= ' @@';
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
      $new_length = $hunk->getNewLen();
      $new_offset = $hunk->getNewOffset();

      for ($i = 0; $i < $new_length; $i++) {
        $offsets[$n] = $new_offset + $i;
        $n++;
      }
    }

    return $offsets;
  }

  private function getIndentDepth($text, $tab_width) {
    $len = strlen($text);

    $depth = 0;
    for ($ii = 0; $ii < $len; $ii++) {
      $c = $text[$ii];

      // If this is a space, increase the indent depth by 1.
      if ($c == ' ') {
        $depth++;
        continue;
      }

      // If this is a tab, increase the indent depth to the next tabstop.

      // For example, if the tab width is 4, these sequences both lead us to
      // a visual width of 8, i.e. the cursor will be in the 8th column:
      //
      //   <tab><tab>
      //   <space><tab><space><space><space><tab>

      if ($c == "\t") {
        $depth = ($depth + $tab_width);
        $depth = $depth - ($depth % $tab_width);
        continue;
      }

      break;
    }

    return $depth;
  }

  private function getCharacterCountForVisualWhitespace(
    $text,
    $depth,
    $tab_width) {

    // Here, we know the visual indent depth of a line has been increased by
    // some amount (for example, 6 characters).

    // We want to find the largest whitespace prefix of the string we can
    // which still fits into that amount of visual space.

    // In most cases, this is very easy. For example, if the string has been
    // indented by two characters and the string begins with two spaces, that's
    // a perfect match.

    // However, if the string has been indented by 7 characters, the tab width
    // is 8, and the string begins with "<space><space><tab>", we can only
    // mark the two spaces as an indent change. These cases are unusual.

    $character_depth = 0;
    $visual_depth = 0;

    $len = strlen($text);
    for ($ii = 0; $ii < $len; $ii++) {
      if ($visual_depth >= $depth) {
        break;
      }

      $c = $text[$ii];

      if ($c == ' ') {
        $character_depth++;
        $visual_depth++;
        continue;
      }

      if ($c == "\t") {
        // Figure out how many visual spaces we have until the next tabstop.
        $tab_visual = ($visual_depth + $tab_width);
        $tab_visual = $tab_visual - ($tab_visual % $tab_width);
        $tab_visual = ($tab_visual - $visual_depth);

        // If this tab would take us over the limit, we're all done.
        $remaining_depth = ($depth - $visual_depth);
        if ($remaining_depth < $tab_visual) {
          break;
        }

        $character_depth++;
        $visual_depth += $tab_visual;
        continue;
      }

      break;
    }

    return $character_depth;
  }

  private function updateChangeTypesForNormalization() {
    if (!$this->getNormalized()) {
      return;
    }

    // If we've parsed based on a normalized diff alignment, we may currently
    // believe some lines are unchanged when they have actually changed. This
    // happens when:
    //
    //   - a line changes;
    //   - the change is a kind of change we normalize away when aligning the
    //     diff, like an indentation change;
    //   - we normalize the change away to align the diff; and so
    //   - the old and new copies of the line are now aligned in the new
    //     normalized diff.
    //
    // Then we end up with an alignment where the two lines that differ only
    // in some some trivial way are aligned. This is great, and exactly what
    // we're trying to accomplish by doing all this alignment stuff in the
    // first place.
    //
    // However, in this case the correctly-aligned lines will be incorrectly
    // marked as unchanged because the diff alorithm was fed normalized copies
    // of the lines, and these copies truly weren't any different.
    //
    // When lines are aligned and marked identical, but they're not actually
    // identical, we now mark them as changed. The rest of the processing will
    // figure out how to render them appropritely.

    $new = $this->getNewLines();
    $old = $this->getOldLines();
    foreach ($old as $key => $o) {
      $n = $new[$key];

      if (!$o || !$n) {
        continue;
      }

      if ($o['type'] === null && $n['type'] === null) {
        if ($o['text'] !== $n['text']) {
          $old[$key]['type'] = '-';
          $new[$key]['type'] = '+';
        }
      }
    }

    $this->setOldLines($old);
    $this->setNewLines($new);
  }


}
