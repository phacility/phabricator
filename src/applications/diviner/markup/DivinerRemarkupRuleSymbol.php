<?php

final class DivinerRemarkupRuleSymbol extends PhutilRemarkupRule {

  public function apply($text) {
    return $this->replaceHTML(
      '/(?:^|\B)@{(?:(?P<type>[^:]+?):)?(?P<name>[^}]+?)}/',
      array($this, 'markupSymbol'),
      $text);
  }

  public function markupSymbol($matches) {
    $type = $matches['type'];
    $name = $matches['name'];

    // Collapse sequences of whitespace into a single space.
    $name = preg_replace('/\s+/', ' ', $name);

    $book = null;
    if (strpos($type, '@') !== false) {
      list($type, $book) = explode('@', $type, 2);
    }

    // TODO: This doesn't actually do anything useful yet.

    $link = phutil_tag(
      'a',
      array(
        'href' => '#',
      ),
      $name);

    return $this->getEngine()->storeText($link);
  }

}
