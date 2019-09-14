<?php

final class PhutilRemarkupHighlightRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 1000.0;
  }

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    return $this->replaceHTML(
      '@!!(.+?)(!{2,})@',
      array($this, 'applyCallback'),
      $text);
  }

  protected function applyCallback(array $matches) {
    // Remove the two exclamation points that represent syntax.
    $excitement = substr($matches[2], 2);

    // If the internal content consists of ONLY exclamation points, leave it
    // untouched so "!!!!!" is five exclamation points instead of one
    // highlighted exclamation point.
    if (preg_match('/^!+\z/', $matches[1])) {
      return $matches[0];
    }

    // $excitement now has two fewer !'s than we started with.
    return hsprintf('<span class="remarkup-highlight">%s%s</span>',
      $matches[1], $excitement);

  }

}
