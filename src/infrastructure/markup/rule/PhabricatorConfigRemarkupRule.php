<?php

final class PhabricatorConfigRemarkupRule
  extends PhutilRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '(@{config:([^}]+)})',
      array($this, 'markupConfig'),
      $text);
  }

  public function getPriority() {
    // We're reusing the Diviner atom syntax, so make sure we evaluate before
    // the Diviner rule evaluates.
    return id(new DivinerSymbolRemarkupRule())->getPriority() - 1;
  }

  public function markupConfig(array $matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $config_key = $matches[1];

    try {
      $option = PhabricatorEnv::getEnvConfig($config_key);
    } catch (Exception $ex) {
      return $matches[0];
    }

    $is_text = $this->getEngine()->isTextMode();
    $is_html_mail = $this->getEngine()->isHTMLMailMode();

    if ($is_text || $is_html_mail) {
      return pht('"%s"', $config_key);
    }

    $link = phutil_tag(
      'a',
      array(
        'href' => urisprintf('/config/edit/%s/', $config_key),
        'target' => '_blank',
      ),
      $config_key);

    return $this->getEngine()->storeText($link);
  }

}
