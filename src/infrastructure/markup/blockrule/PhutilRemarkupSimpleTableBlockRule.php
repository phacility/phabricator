<?php

final class PhutilRemarkupSimpleTableBlockRule extends PhutilRemarkupBlockRule {

  public function getMatchingLineCount(array $lines, $cursor) {
    $num_lines = 0;
    while (isset($lines[$cursor])) {
      if (preg_match('/^(\s*\|.*+\n?)+$/', $lines[$cursor])) {
        $num_lines++;
        $cursor++;
      } else {
        break;
      }
    }

    return $num_lines;
  }

  public function markupText($text, $children) {
    $matches = array();

    $rows = array();
    foreach (explode("\n", $text) as $line) {
      // Ignore ending delimiters.
      $line = rtrim($line, '|');

      // NOTE: The complexity in this regular expression allows us to match
      // a table like "| a | [[ href | b ]] | c |".

      preg_match_all(
        '/\|'.
        '('.
          '(?:'.
            '(?:\\[\\[.*?\\]\\])'. // [[ ... | ... ]], a link
            '|'.
              '(?:[^|[]+)'.          // Anything but "|" or "[".
            '|'.
              '(?:\\[[^\\|[])'.      // "[" followed by anything but "[" or "|"
          ')*'.
        ')/', $line, $matches);

      $any_header = false;
      $any_content = false;

      $cells = array();
      foreach ($matches[1] as $cell) {
        $cell = trim($cell);

        // If this row only has empty cells and "--" cells, and it has at
        // least one "--" cell, it's marking the rows above as <th> cells
        // instead of <td> cells.

        // If it has other types of cells, it's always a content row.

        // If it has only empty cells, it's an empty row.

        if (strlen($cell)) {
          if (preg_match('/^--+\z/', $cell)) {
            $any_header = true;
          } else {
            $any_content = true;
          }
        }

        $cells[] = array('type' => 'td', 'content' => $this->applyRules($cell));
      }

      $is_header = ($any_header && !$any_content);

      if (!$is_header) {
        $rows[] = array('type' => 'tr', 'content' => $cells);
      } else if ($rows) {
        // Mark previous row with headings.
        foreach ($cells as $i => $cell) {
          if ($cell['content']) {
            $last_key = last_key($rows);
            if (!isset($rows[$last_key]['content'][$i])) {
              // If this row has more cells than the previous row, there may
              // not be a cell above this one to turn into a <th />.
              continue;
            }

            $rows[$last_key]['content'][$i]['type'] = 'th';
          }
        }
      }
    }

    if (!$rows) {
      return $this->applyRules($text);
    }

    return $this->renderRemarkupTable($rows);
  }

}
