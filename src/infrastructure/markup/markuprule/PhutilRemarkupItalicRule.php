<?php

final class PhutilRemarkupItalicRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 1000.0;
  }

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    return $this->replaceHTML(
      '@(?<!:)//(.+?)//@s',
      array($this, 'applyCallback'),
      $text);
  }

  protected function applyCallback(array $matches) {
    return hsprintf('<em>%s</em>', $matches[1]);
  }

}
