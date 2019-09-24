<?php

final class PhutilRemarkupHeaderBlockRule extends PhutilRemarkupBlockRule {

  public function getMatchingLineCount(array $lines, $cursor) {
    $num_lines = 0;
    if (preg_match('/^(={1,5}|#{2,5}|# ).*+$/', $lines[$cursor])) {
      $num_lines = 1;
    } else {
      if (isset($lines[$cursor + 1])) {
        $line = $lines[$cursor].$lines[$cursor + 1];
        if (preg_match('/^([^\n]+)\n[-=]{2,}\s*$/', $line)) {
          $num_lines = 2;
          $cursor++;
        }
      }
    }

    if ($num_lines) {
      $cursor++;
      while (isset($lines[$cursor]) && !strlen(trim($lines[$cursor]))) {
        $num_lines++;
        $cursor++;
      }
    }

    return $num_lines;
  }

  const KEY_HEADER_TOC = 'headers.toc';

  public function markupText($text, $children) {
    $text = trim($text);

    $lines = phutil_split_lines($text);
    if (count($lines) > 1) {
      $level = ($lines[1][0] == '=') ? 1 : 2;
      $text = trim($lines[0]);
    } else {
      $level = 0;
      for ($ii = 0; $ii < min(5, strlen($text)); $ii++) {
        if ($text[$ii] == '=' || $text[$ii] == '#') {
          ++$level;
        } else {
          break;
        }
      }
      $text = trim($text, ' =#');
    }

    $engine = $this->getEngine();

    if ($engine->isTextMode()) {
      $char = ($level == 1) ? '=' : '-';
      return $text."\n".str_repeat($char, phutil_utf8_strlen($text));
    }

    $use_anchors = $engine->getConfig('header.generate-toc');

    $anchor = null;
    if ($use_anchors) {
      $anchor = $this->generateAnchor($level, $text);
    }

    $text = phutil_tag(
      'h'.($level + 1),
      array(
        'class' => 'remarkup-header',
      ),
      array($anchor, $this->applyRules($text)));

    return $text;
  }

  private function generateAnchor($level, $text) {
    $engine = $this->getEngine();

    // When a document contains a link inside a header, like this:
    //
    //  = [[ http://wwww.example.com/ | example ]] =
    //
    // ...we want to generate a TOC entry with just "example", but link the
    // header itself. We push the 'toc' state so all the link rules generate
    // just names.
    $engine->pushState('toc');
      $plain_text = $text;
      $plain_text = $this->applyRules($plain_text);
      $plain_text = $engine->restoreText($plain_text);
    $engine->popState('toc');

    $anchor = self::getAnchorNameFromHeaderText($plain_text);

    if (!strlen($anchor)) {
      return null;
    }

    $base = $anchor;

    $key = self::KEY_HEADER_TOC;
    $anchors = $engine->getTextMetadata($key, array());

    $suffix = 1;
    while (isset($anchors[$anchor])) {
      $anchor = $base.'-'.$suffix;
      $anchor = trim($anchor, '-');
      $suffix++;
    }

    $anchors[$anchor] = array($level, $plain_text);
    $engine->setTextMetadata($key, $anchors);

    return phutil_tag(
      'a',
      array(
        'name' => $anchor,
      ),
      '');
  }

  public static function renderTableOfContents(PhutilRemarkupEngine $engine) {

    $key = self::KEY_HEADER_TOC;
    $anchors = $engine->getTextMetadata($key, array());

    if (count($anchors) < 2) {
      // Don't generate a TOC if there are no headers, or if there's only
      // one header (since such a TOC would be silly).
      return null;
    }

    $depth = 0;
    $toc = array();
    foreach ($anchors as $anchor => $info) {
      list($level, $name) = $info;

      while ($depth < $level) {
        $toc[] = hsprintf('<ul>');
        $depth++;
      }
      while ($depth > $level) {
        $toc[] = hsprintf('</ul>');
        $depth--;
      }

      $toc[] = phutil_tag(
        'li',
        array(),
        phutil_tag(
          'a',
          array(
            'href' => '#'.$anchor,
          ),
          $name));
    }
    while ($depth > 0) {
      $toc[] = hsprintf('</ul>');
      $depth--;
    }

    return phutil_implode_html("\n", $toc);
  }

  public static function getAnchorNameFromHeaderText($text) {
    $anchor = phutil_utf8_strtolower($text);
    $anchor = PhutilRemarkupAnchorRule::normalizeAnchor($anchor);

    // Truncate the fragment to something reasonable.
    $anchor = id(new PhutilUTF8StringTruncator())
      ->setMaximumGlyphs(32)
      ->setTerminator('')
      ->truncateString($anchor);

    // If the fragment is terminated by a word which "The U.S. Government
    // Printing Office Style Manual" normally discourages capitalizing in
    // titles, discard it. This is an arbitrary heuristic intended to avoid
    // awkward hanging words in anchors.
    $anchor = preg_replace(
      '/-(a|an|the|at|by|for|in|of|on|per|to|up|and|as|but|if|or|nor)\z/',
      '',
      $anchor);

    return $anchor;
  }

}
