<?php

final class PhutilRemarkupQuotesBlockRule
  extends PhutilRemarkupQuotedBlockRule {

  public function getMatchingLineCount(array $lines, $cursor) {
    $pos = $cursor;

    if (preg_match('/^>/', $lines[$pos])) {
      do {
        ++$pos;
      } while (isset($lines[$pos]) && preg_match('/^>/', $lines[$pos]));
    }

    return ($pos - $cursor);
  }

  public function extractChildText($text) {
    return array('', $this->normalizeQuotedBody($text));
  }

  public function markupText($text, $children) {
    if ($this->getEngine()->isTextMode()) {
      return $this->getQuotedText($children);
    }

    $attributes = array();
    if ($this->getEngine()->isHTMLMailMode()) {
      $style = array(
        'border-left: 3px solid #a7b5bf;',
        'color: #464c5c;',
        'font-style: italic;',
        'margin: 4px 0 12px 0;',
        'padding: 4px 12px;',
        'background-color: #f8f9fc;',
      );

      $attributes['style'] = implode(' ', $style);
    }

    return phutil_tag(
      'blockquote',
      $attributes,
      $children);
  }

}
