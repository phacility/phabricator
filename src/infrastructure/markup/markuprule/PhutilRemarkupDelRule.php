<?php

final class PhutilRemarkupDelRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 1000.0;
  }

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    return $this->replaceHTML(
      '@(?<!~)~~([^\s~].*?~*)~~@s',
      array($this, 'applyCallback'),
      $text);
  }

  protected function applyCallback(array $matches) {
    return hsprintf('<del>%s</del>', $matches[1]);
  }

}
