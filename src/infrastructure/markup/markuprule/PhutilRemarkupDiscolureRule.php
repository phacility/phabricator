<?php

final class PhutilRemarkupDiscolureRule extends PhutilRemarkupRule {

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    /**
     * Matches the details markdown tag
     * with a summary element
     * https://regex101.com/r/Pe2G41/1
     */
    return $this->replaceHTML(
      '@(?<!:)<details>(.+?)<summary>(.+?)</summary>(.+?)</details>@s',
      array($this, 'applyCallback'),
      $text);
  }

  protected function applyCallback(array $matches) {
    return hsprintf("<details>\n<summary>%s</summary>\n\n%s\n</details>", $matches[2], $matches[3]);
  }

}
