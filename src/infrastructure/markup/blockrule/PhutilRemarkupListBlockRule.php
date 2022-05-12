<?php

final class PhutilRemarkupListBlockRule extends PhutilRemarkupBlockRule {

  /**
   * This rule must apply before the Code block rule because it needs to
   * win blocks which begin `  - Lorem ipsum`.
   */
  public function getPriority() {
    return 400;
  }

  public function getMatchingLineCount(array $lines, $cursor) {
    $num_lines = 0;

    $first_line = $cursor;
    $is_one_line = false;
    while (isset($lines[$cursor])) {
      if (!$num_lines) {
        if (preg_match(self::START_BLOCK_PATTERN, $lines[$cursor])) {
          $num_lines++;
          $cursor++;
          $is_one_line = true;
          continue;
        }
      } else {
        if (preg_match(self::CONT_BLOCK_PATTERN, $lines[$cursor])) {
          $num_lines++;
          $cursor++;
          $is_one_line = false;
          continue;
        }

        // Allow lists to continue across multiple paragraphs, as long as lines
        // are indented or a single empty line separates indented lines.

        $this_empty = !strlen(trim($lines[$cursor]));
        $this_indented = preg_match('/^ /', $lines[$cursor]);

        $next_empty = true;
        $next_indented = false;
        if (isset($lines[$cursor + 1])) {
          $next_empty = !strlen(trim($lines[$cursor + 1]));
          $next_indented = preg_match('/^ /', $lines[$cursor + 1]);
        }

        if ($this_empty || $this_indented) {
          if (($this_indented && !$this_empty) ||
              ($next_indented && !$next_empty)) {
            $num_lines++;
            $cursor++;
            continue;
          }
        }

        if ($this_empty) {
          $num_lines++;
        }
      }

      break;
    }

    // If this list only has one item in it, and the list marker is "#", and
    // it's not the last line in the input, parse it as a header instead of a
    // list. This produces better behavior for alternate Markdown headers.

    if ($is_one_line) {
      if (($first_line + $num_lines) < count($lines)) {
        if (strncmp($lines[$first_line], '#', 1) === 0) {
          return 0;
        }
      }
    }

    return $num_lines;
  }

  /**
   * The maximum sub-list depth you can nest to. Avoids silliness and blowing
   * the stack.
   */
  const MAXIMUM_LIST_NESTING_DEPTH = 12;
  const START_BLOCK_PATTERN = '@^\s*(?:[-*#]+|([1-9][0-9]*)[.)]|\[\D?\])\s+@';
  const CONT_BLOCK_PATTERN = '@^\s*(?:[-*#]+|[0-9]+[.)]|\[\D?\])\s+@';
  const STRIP_BLOCK_PATTERN = '@^\s*(?:[-*#]+|[0-9]+[.)])\s*@';

  public function markupText($text, $children) {
    $items = array();
    $lines = explode("\n", $text);

    // We allow users to delimit lists using either differing indentation
    // levels:
    //
    //   - a
    //     - b
    //
    // ...or differing numbers of item-delimiter characters:
    //
    //   - a
    //   -- b
    //
    // If they use the second style but block-indent the whole list, we'll
    // get the depth counts wrong for the first item. To prevent this,
    // un-indent every item by the minimum indentation level for the whole
    // block before we begin parsing.

    $regex = self::START_BLOCK_PATTERN;
    $min_space = PHP_INT_MAX;
    foreach ($lines as $ii => $line) {
      $matches = null;
      if (preg_match($regex, $line)) {
        $regex = self::CONT_BLOCK_PATTERN;
        if (preg_match('/^(\s+)/', $line, $matches)) {
          $space = strlen($matches[1]);
        } else {
          $space = 0;
        }
        $min_space = min($min_space, $space);
      }
    }

    $regex = self::START_BLOCK_PATTERN;
    if ($min_space) {
      foreach ($lines as $key => $line) {
        if (preg_match($regex, $line)) {
          $regex = self::CONT_BLOCK_PATTERN;
          $lines[$key] = substr($line, $min_space);
        }
      }
    }


    // The input text may have linewraps in it, like this:
    //
    //   - derp derp derp derp
    //     derp derp derp derp
    //   - blarp blarp blarp blarp
    //
    // Group text lines together into list items, stored in $items. So the
    // result in the above case will be:
    //
    //   array(
    //     array(
    //       "- derp derp derp derp",
    //       "  derp derp derp derp",
    //     ),
    //     array(
    //       "- blarp blarp blarp blarp",
    //     ),
    //   );

    $item = array();
    $starts_at = null;
    $regex = self::START_BLOCK_PATTERN;
    foreach ($lines as $line) {
      $match = null;
      if (preg_match($regex, $line, $match)) {
        if (!$starts_at && !empty($match[1])) {
          $starts_at = $match[1];
        }
        $regex = self::CONT_BLOCK_PATTERN;
        if ($item) {
          $items[] = $item;
          $item = array();
        }
      }
      $item[] = $line;
    }
    if ($item) {
      $items[] = $item;
    }
    if (!$starts_at) {
      $starts_at = 1;
    }


    // Process each item to normalize the text, remove line wrapping, and
    // determine its depth (indentation level) and style (ordered vs unordered).
    //
    // We preserve consecutive linebreaks and interpret them as paragraph
    // breaks.
    //
    // Given the above example, the processed array will look like:
    //
    //   array(
    //     array(
    //       'text'  => 'derp derp derp derp derp derp derp derp',
    //       'depth' => 0,
    //       'style' => '-',
    //     ),
    //     array(
    //       'text'  => 'blarp blarp blarp blarp',
    //       'depth' => 0,
    //       'style' => '-',
    //     ),
    //   );

    $has_marks = false;
    foreach ($items as $key => $item) {
      // Trim space around newlines, to strip trailing whitespace and formatting
      // indentation.
      $item = preg_replace('/ *(\n+) */', '\1', implode("\n", $item));

      // Replace single newlines with a space. Preserve multiple newlines as
      // paragraph breaks.
      $item = preg_replace('/(?<!\n)\n(?!\n)/', ' ', $item);

      $item = rtrim($item);

      if (!strlen($item)) {
        unset($items[$key]);
        continue;
      }

      $matches = null;
      if (preg_match('/^\s*([-*#]{2,})/', $item, $matches)) {
        // Alternate-style indents; use number of list item symbols.
        $depth = strlen($matches[1]) - 1;
      } else if (preg_match('/^(\s+)/', $item, $matches)) {
        // Markdown-style indents; use indent depth.
        $depth = strlen($matches[1]);
      } else {
        $depth = 0;
      }

      if (preg_match('/^\s*(?:#|[0-9])/', $item)) {
        $style = '#';
      } else {
        $style = '-';
      }

      // Strip leading indicators off the item.
      $text = preg_replace(self::STRIP_BLOCK_PATTERN, '', $item);

      // Look for "[]", "[ ]", "[*]", "[x]", etc., which we render as a
      // checkbox. We don't render [1], [2], etc., as checkboxes, as these
      // are often used as footnotes.
      $mark = null;
      $matches = null;
      if (preg_match('/^\s*\[(\D?)\]\s*/', $text, $matches)) {
        if (strlen(trim($matches[1]))) {
          $mark = true;
        } else {
          $mark = false;
        }
        $has_marks = true;
        $text = substr($text, strlen($matches[0]));
      }

      $items[$key] = array(
        'text'  => $text,
        'depth' => $depth,
        'style' => $style,
        'mark'  => $mark,
      );
    }
    $items = array_values($items);


    // Users can create a sub-list by indenting any deeper amount than the
    // previous list, so these are both valid:
    //
    //   - a
    //     - b
    //
    //   - a
    //       - b
    //
    // In the former case, we'll have depths (0, 2). In the latter case, depths
    // (0, 4). We don't actually care about how many spaces there are, only
    // how many list indentation levels (that is, we want to map both of
    // those cases to (0, 1), indicating "outermost list" and "first sublist").
    //
    // This is made more complicated because lists at two different indentation
    // levels might be at the same list level:
    //
    //   - a
    //     - b
    //   - c
    //       - d
    //
    // Here, 'b' and 'd' are at the same list level (2) but different indent
    // levels (2, 4).
    //
    // Users can also create "staircases" like this:
    //
    //       - a
    //     - b
    //   # c
    //
    // While this is silly, we'd like to render it as faithfully as possible.
    //
    // In order to do this, we convert the list of nodes into a tree,
    // normalizing indentation levels and inserting dummy nodes as necessary to
    // make the tree well-formed. See additional notes at buildTree().
    //
    // In the case above, the result is a tree like this:
    //
    //   - <null>
    //     - <null>
    //       - a
    //     - b
    //   # c

    $l = 0;
    $r = count($items);
    $tree = $this->buildTree($items, $l, $r, $cur_level = 0);


    // We may need to open a list on a <null> node, but they do not have
    // list style information yet. We need to propagate list style information
    // backward through the tree. In the above example, the tree now looks
    // like this:
    //
    //   - <null (style=#)>
    //     - <null (style=-)>
    //       - a
    //     - b
    //   # c

    $this->adjustTreeStyleInformation($tree);

    // Finally, we have enough information to render the tree.

    $out = $this->renderTree($tree, 0, $has_marks, $starts_at);

    if ($this->getEngine()->isTextMode()) {
      $out = implode('', $out);
      $out = rtrim($out, "\n");
      $out = preg_replace('/ +$/m', '', $out);
      return $out;
    }

    return phutil_implode_html('', $out);
  }

  /**
   * See additional notes in @{method:markupText}.
   */
  private function buildTree(array $items, $l, $r, $cur_level) {
    if ($l == $r) {
      return array();
    }

    if ($cur_level > self::MAXIMUM_LIST_NESTING_DEPTH) {
      // This algorithm is recursive and we don't need you blowing the stack
      // with your oh-so-clever 50,000-item-deep list. Cap indentation levels
      // at a reasonable number and just shove everything deeper up to this
      // level.
      $nodes = array();
      for ($ii = $l; $ii < $r; $ii++) {
        $nodes[] = array(
          'level' => $cur_level,
          'items' => array(),
        ) + $items[$ii];
      }
      return $nodes;
    }

    $min = $l;
    for ($ii = $r - 1; $ii >= $l; $ii--) {
      if ($items[$ii]['depth'] <= $items[$min]['depth']) {
        $min = $ii;
      }
    }

    $min_depth = $items[$min]['depth'];

    $nodes = array();
    if ($min != $l) {
      $nodes[] = array(
        'text'    => null,
        'level'   => $cur_level,
        'style'   => null,
        'mark'    => null,
        'items'   => $this->buildTree($items, $l, $min, $cur_level + 1),
      );
    }

    $last = $min;
    for ($ii = $last + 1; $ii < $r; $ii++) {
      if ($items[$ii]['depth'] == $min_depth) {
        $nodes[] = array(
          'level' => $cur_level,
          'items' => $this->buildTree($items, $last + 1, $ii, $cur_level + 1),
        ) + $items[$last];
        $last = $ii;
      }
    }
    $nodes[] = array(
      'level' => $cur_level,
      'items' => $this->buildTree($items, $last + 1, $r, $cur_level + 1),
    ) + $items[$last];

    return $nodes;
  }


  /**
   * See additional notes in @{method:markupText}.
   */
  private function adjustTreeStyleInformation(array &$tree) {
    // The effect here is just to walk backward through the nodes at this level
    // and apply the first style in the list to any empty nodes we inserted
    // before it. As we go, also recurse down the tree.

    $style = '-';
    for ($ii = count($tree) - 1; $ii >= 0; $ii--) {
      if ($tree[$ii]['style'] !== null) {
        // This is the earliest node we've seen with style, so set the
        // style to its style.
        $style = $tree[$ii]['style'];
      } else {
        // This node has no style, so apply the current style.
        $tree[$ii]['style'] = $style;
      }
      if ($tree[$ii]['items']) {
        $this->adjustTreeStyleInformation($tree[$ii]['items']);
      }
    }
  }


  /**
   * See additional notes in @{method:markupText}.
   */
  private function renderTree(
    array $tree,
    $level,
    $has_marks,
    $starts_at = 1) {

    $style = idx(head($tree), 'style');

    $out = array();

    if (!$this->getEngine()->isTextMode()) {
      switch ($style) {
        case '#':
          $tag = 'ol';
          break;
        case '-':
          $tag = 'ul';
          break;
      }

      $start_attr = null;
      if (ctype_digit(phutil_string_cast($starts_at)) && $starts_at > 1) {
        $start_attr = hsprintf(' start="%d"', $starts_at);
      }

      if ($has_marks) {
        $out[] = hsprintf(
          '<%s class="remarkup-list remarkup-list-with-checkmarks"%s>',
          $tag,
          $start_attr);
      } else {
        $out[] = hsprintf(
          '<%s class="remarkup-list"%s>',
          $tag,
          $start_attr);
      }

      $out[] = "\n";
    }

    $number = $starts_at;
    foreach ($tree as $item) {
      if ($this->getEngine()->isTextMode()) {
        if ($item['text'] === null) {
          // Don't render anything.
        } else {
          $indent = str_repeat(' ', 2 * $level);
          $out[] = $indent;
          if ($item['mark'] !== null) {
            if ($item['mark']) {
              $out[] = '[X] ';
            } else {
              $out[] = '[ ] ';
            }
          } else {
            switch ($style) {
              case '#':
                $out[] = $number.'. ';
                $number++;
                break;
              case '-':
                $out[] = '- ';
                break;
            }
          }

          $parts = preg_split('/\n{2,}/', $item['text']);
          foreach ($parts as $key => $part) {
            if ($key != 0) {
              $out[] = "\n\n  ".$indent;
            }
            $out[] = $this->applyRules($part);
          }
          $out[] = "\n";
        }
      } else {
        if ($item['text'] === null) {
          $out[] = hsprintf('<li class="remarkup-list-item phantom-item">');
        } else {
          if ($item['mark'] !== null) {
            if ($item['mark'] == true) {
              $out[] = hsprintf(
                '<li class="remarkup-list-item remarkup-checked-item">');
            } else {
              $out[] = hsprintf(
                '<li class="remarkup-list-item remarkup-unchecked-item">');
            }
            $out[] = phutil_tag(
              'input',
              array(
                'type' => 'checkbox',
                'checked' => ($item['mark'] ? 'checked' : null),
                'disabled' => 'disabled',
              ));
            $out[] = ' ';
          } else {
            $out[] = hsprintf('<li class="remarkup-list-item">');
          }

          $parts = preg_split('/\n{2,}/', $item['text']);
          foreach ($parts as $key => $part) {
            if ($key != 0) {
              $out[] = array(
                "\n",
                phutil_tag('br'),
                phutil_tag('br'),
                "\n",
              );
            }
            $out[] = $this->applyRules($part);
          }
        }
      }

      if ($item['items']) {
        $subitems = $this->renderTree($item['items'], $level + 1, $has_marks);
        foreach ($subitems as $i) {
          $out[] = $i;
        }
      }
      if (!$this->getEngine()->isTextMode()) {
        $out[] = hsprintf("</li>\n");
      }
    }

    if (!$this->getEngine()->isTextMode()) {
      switch ($style) {
        case '#':
          $out[] = hsprintf('</ol>');
          break;
        case '-':
          $out[] = hsprintf('</ul>');
          break;
      }
    }

    return $out;
  }

}
