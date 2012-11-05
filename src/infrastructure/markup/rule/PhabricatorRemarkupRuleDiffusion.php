<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleDiffusion
  extends PhutilRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '@\br([A-Z]+[a-f0-9]+)\b@',
      array($this, 'markupDiffusionLink'),
      $text);
  }

  public function markupDiffusionLink($matches) {
    return $this->getEngine()->storeText(
      '<a href="/r'.$matches[1].'">r'.$matches[1].'</a>');
  }

}
