<?php

final class PhutilRemarkupBoldRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 1000.0;
  }

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    return $this->replaceHTML(
      '@\\*\\*(.+?)\\*\\*@s',
      array($this, 'applyCallback'),
      $text);
  }

  protected function applyCallback(array $matches) {
    if ($this->getEngine()->isAnchorMode()) {
      return $matches[1];
    }

    return hsprintf('<strong>%s</strong>', $matches[1]);
  }

}
