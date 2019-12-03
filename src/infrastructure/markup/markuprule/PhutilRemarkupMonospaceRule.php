<?php

final class PhutilRemarkupMonospaceRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 100.0;
  }

  public function apply($text) {
    // NOTE: We don't require a trailing non-boundary on the backtick syntax,
    // to permit the use case of naming and pluralizing a class, like
    // "Load all the `PhutilArray`s and then iterate over them." In theory, the
    // required \B on the leading backtick should protect us from most
    // collateral damage.

    return preg_replace_callback(
      '@##([\s\S]+?)##|\B`(.+?)`@',
      array($this, 'markupMonospacedText'),
      $text);
  }

  protected function markupMonospacedText(array $matches) {
    if ($this->getEngine()->isTextMode()) {
      $result = $matches[0];

    } else
    if ($this->getEngine()->isHTMLMailMode()) {
      $match = isset($matches[2]) ? $matches[2] : $matches[1];
      $result = phutil_tag(
        'tt',
        array(
          'style' => 'background: #ebebeb; font-size: 13px;',
        ),
        $match);

    } else {
      $match = isset($matches[2]) ? $matches[2] : $matches[1];
      $result = phutil_tag(
        'tt',
        array(
          'class' => 'remarkup-monospaced',
        ),
        $match);
    }

    return $this->getEngine()->storeText($result);
  }

}
