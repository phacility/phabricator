<?php

final class PhutilProseDiff extends Phobject {

  private $parts = array();

  public function addPart($type, $text) {
    $this->parts[] = array(
      'type' => $type,
      'text' => $text,
    );
    return $this;
  }

  public function getParts() {
    return $this->parts;
  }

  /**
   * Get diff parts, but replace large blocks of unchanged text with "."
   * parts representing missing context.
   */
  public function getSummaryParts() {
    $parts = $this->getParts();

    $head_key = head_key($parts);
    $last_key = last_key($parts);

    $results = array();
    foreach ($parts as $key => $part) {
      $is_head = ($key == $head_key);
      $is_last = ($key == $last_key);

      switch ($part['type']) {
        case '=':
          $pieces = $this->splitTextForSummary($part['text']);

          if ($is_head || $is_last) {
            $need = 2;
          } else {
            $need = 3;
          }

          // We don't have enough pieces to omit anything, so just continue.
          if (count($pieces) < $need) {
            $results[] = $part;
            break;
          }

          if (!$is_head) {
            $results[] = array(
              'type' => '=',
              'text' => head($pieces),
            );
          }

          $results[] = array(
            'type' => '.',
            'text' => null,
          );

          if (!$is_last) {
            $results[] = array(
              'type' => '=',
              'text' => last($pieces),
            );
          }
          break;
        default:
          $results[] = $part;
          break;
      }
    }

    return $results;
  }


  public function reorderParts() {
    // Reorder sequences of removed and added sections to put all the "-"
    // parts together first, then all the "+" parts together. This produces
    // a more human-readable result than intermingling them.

    $o_run = array();
    $n_run = array();
    $result = array();
    foreach ($this->parts as $part) {
      $type = $part['type'];
      switch ($type) {
        case '-':
          $o_run[] = $part;
          break;
        case '+':
          $n_run[] = $part;
          break;
        default:
          if ($o_run || $n_run) {
            foreach ($this->combineRuns($o_run, $n_run) as $merged_part) {
              $result[] = $merged_part;
            }
            $o_run = array();
            $n_run = array();
          }
          $result[] = $part;
          break;
      }
    }

    if ($o_run || $n_run) {
      foreach ($this->combineRuns($o_run, $n_run) as $part) {
        $result[] = $part;
      }
    }

    // Now, combine consecuitive runs of the same type of change (like a
    // series of "-" parts) into a single run.
    $combined = array();

    $last = null;
    $last_text = null;
    foreach ($result as $part) {
      $type = $part['type'];

      if ($last !== $type) {
        if ($last !== null) {
          $combined[] = array(
            'type' => $last,
            'text' => $last_text,
          );
        }
        $last_text = null;
        $last = $type;
      }

      $last_text .= $part['text'];
    }

    if ($last_text !== null) {
      $combined[] = array(
        'type' => $last,
        'text' => $last_text,
      );
    }

    $this->parts = $combined;

    return $this;
  }

  private function combineRuns($o_run, $n_run) {
    $o_merge = $this->mergeParts($o_run);
    $n_merge = $this->mergeParts($n_run);

    // When removed and added blocks share a prefix or suffix, we sometimes
    // want to count it as unchanged (for example, if it is whitespace) but
    // sometimes want to count it as changed (for example, if it is a word
    // suffix like "ing"). Find common prefixes and suffixes of these layout
    // characters and emit them as "=" (unchanged) blocks.

    $layout_characters = array(
      ' ' => true,
      "\n" => true,
      '.' => true,
      '!' => true,
      ',' => true,
      '?' => true,
      ']' => true,
      '[' => true,
      '(' => true,
      ')' => true,
      '<' => true,
      '>' => true,
    );

    $o_text = $o_merge['text'];
    $n_text = $n_merge['text'];
    $o_len = strlen($o_text);
    $n_len = strlen($n_text);
    $min_len = min($o_len, $n_len);

    $prefix_len = 0;
    for ($pos = 0; $pos < $min_len; $pos++) {
      $o = $o_text[$pos];
      $n = $n_text[$pos];
      if ($o !== $n) {
        break;
      }
      if (empty($layout_characters[$o])) {
        break;
      }
      $prefix_len++;
    }

    $suffix_len = 0;
    for ($pos = 0; $pos < ($min_len - $prefix_len); $pos++) {
      $o = $o_text[$o_len - ($pos + 1)];
      $n = $n_text[$n_len - ($pos + 1)];
      if ($o !== $n) {
        break;
      }
      if (empty($layout_characters[$o])) {
        break;
      }
      $suffix_len++;
    }

    $results = array();

    if ($prefix_len) {
      $results[] = array(
        'type' => '=',
        'text' => substr($o_text, 0, $prefix_len),
      );
    }

    if ($prefix_len < $o_len) {
      $results[] = array(
        'type' => '-',
        'text' => substr(
          $o_text,
          $prefix_len,
          $o_len - $prefix_len - $suffix_len),
      );
    }

    if ($prefix_len < $n_len) {
      $results[] = array(
        'type' => '+',
        'text' => substr(
          $n_text,
          $prefix_len,
          $n_len - $prefix_len - $suffix_len),
      );
    }

    if ($suffix_len) {
      $results[] = array(
        'type' => '=',
        'text' => substr($o_text, -$suffix_len),
      );
    }

    return $results;
  }

  private function mergeParts(array $parts) {
    $text = '';
    $type = null;
    foreach ($parts as $part) {
      $part_type = $part['type'];
      if ($type === null) {
        $type = $part_type;
      }
      if ($type !== $part_type) {
        throw new Exception(pht('Can not merge parts of dissimilar types!'));
      }
      $text .= $part['text'];
    }

    return array(
      'type' => $type,
      'text' => $text,
    );
  }

  private function splitTextForSummary($text) {
    $matches = null;

    $ok = preg_match('/^(\n*[^\n]+)\n/', $text, $matches);
    if (!$ok) {
      return array($text);
    }

    $head = $matches[1];
    $text = substr($text, strlen($head));

    $ok = preg_match('/\n([^\n]+\n*)\z/', $text, $matches);
    if (!$ok) {
      return array($text);
    }

    $last = $matches[1];
    $text = substr($text, 0, -strlen($last));

    if (!strlen(trim($text))) {
      return array($head, $last);
    } else {
      return array($head, $text, $last);
    }
  }

}
