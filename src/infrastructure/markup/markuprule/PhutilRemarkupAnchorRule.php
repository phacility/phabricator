<?php

final class PhutilRemarkupAnchorRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '/{anchor\s+#([^\s}]+)}/s',
      array($this, 'markupAnchor'),
      $text);
  }

  protected function markupAnchor(array $matches) {
    $engine = $this->getEngine();

    if ($engine->isTextMode()) {
      return null;
    }

    if ($engine->isHTMLMailMode()) {
      return null;
    }

    if ($engine->isAnchorMode()) {
      return null;
    }

    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    if (!self::isValidAnchorName($matches[1])) {
      return $matches[0];
    }

    $tag_view = phutil_tag(
      'a',
      array(
        'name' => $matches[1],
      ),
      '');

    return $this->getEngine()->storeText($tag_view);
  }

  public static function isValidAnchorName($anchor_name) {
    $normal_anchor = self::normalizeAnchor($anchor_name);

    if ($normal_anchor === $anchor_name) {
      return true;
    }

    return false;
  }

  public static function normalizeAnchor($anchor) {
    // Replace all latin characters which are not "a-z" or "0-9" with "-".
    // Preserve other characters, since non-latin letters and emoji work
    // fine in anchors.
    $anchor = preg_replace('/[\x00-\x2F\x3A-\x60\x7B-\x7F]+/', '-', $anchor);
    $anchor = trim($anchor, '-');

    return $anchor;
  }

}
