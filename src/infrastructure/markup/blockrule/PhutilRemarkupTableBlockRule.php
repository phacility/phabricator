<?php

final class PhutilRemarkupTableBlockRule extends PhutilRemarkupBlockRule {

  public function getMatchingLineCount(array $lines, $cursor) {
    $num_lines = 0;

    if (preg_match('/^\s*<table>/i', $lines[$cursor])) {
      $num_lines++;
      $cursor++;

      while (isset($lines[$cursor])) {
        $num_lines++;
        if (preg_match('@</table>\s*$@i', $lines[$cursor])) {
          break;
        }
        $cursor++;
      }
    }

    return $num_lines;
  }

  public function markupText($text, $children) {
    $root = id(new PhutilHTMLParser())
      ->parseDocument($text);

    $nodes = $root->selectChildrenWithTags(array('table'));

    $out = array();
    $seen_table = false;
    foreach ($nodes as $node) {
      if ($node->isContentNode()) {
        $content = $node->getContent();

        if (!strlen(trim($content))) {
          // Ignore whitespace.
          continue;
        }

        // If we find other content, fail the rule. This can happen if the
        // input is two consecutive table tags on one line with some text
        // in between them, which we currently forbid.
        return $text;
      } else {
        // If we have multiple table tags, just return the raw text.
        if ($seen_table) {
          return $text;
        }
        $seen_table = true;

        $out[] = $this->newTable($node);
      }
    }

    if ($this->getEngine()->isTextMode()) {
      return implode('', $out);
    } else {
      return phutil_implode_html('', $out);
    }
  }

  private function newTable(PhutilDOMNode $table) {
    $nodes = $table->selectChildrenWithTags(
      array(
        'colgroup',
        'tr',
      ));

    $colgroup = null;
    $rows = array();

    foreach ($nodes as $node) {
      if ($node->isContentNode()) {
        $content = $node->getContent();

        // If this is whitespace, ignore it.
        if (!strlen(trim($content))) {
          continue;
        }

        // If we have nonempty content between the rows, this isn't a valid
        // table. We can't really do anything reasonable with this, so just
        // fail out and render the raw text.
        return $table->newRawString();
      }

      if ($node->getTagName() === 'colgroup') {
        // This table has multiple "<colgroup />" tags. Just bail out.
        if ($colgroup !== null) {
          return $table->newRawString();
        }

        // This table has a "<colgroup />" after a "<tr />". We could parse
        // this, but just reject it out of an abundance of caution.
        if ($rows) {
          return $table->newRawString();
        }

        $colgroup = $node;
        continue;
      }

      $rows[] = $node;
    }

    $row_specs = array();

    foreach ($rows as $row) {
      $cells = $row->selectChildrenWithTags(array('td', 'th'));

      $cell_specs = array();
      foreach ($cells as $cell) {
        if ($cell->isContentNode()) {
          $content = $node->getContent();

          if (!strlen(trim($content))) {
            continue;
          }

          return $table->newRawString();
        }

        $content = $cell->newRawContentString();
        $content = $this->applyRules($content);

        $cell_specs[] = array(
          'type' => $cell->getTagName(),
          'content' => $content,
        );
      }

      $row_specs[] = array(
        'type' => 'tr',
        'content' => $cell_specs,
      );
    }

    return $this->renderRemarkupTable($row_specs);
  }

}
