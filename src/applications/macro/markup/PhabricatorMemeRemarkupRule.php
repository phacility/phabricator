<?php

final class PhabricatorMemeRemarkupRule extends PhutilRemarkupRule {

  private $images;

  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@{meme,((?:[^}\\\\]+|\\\\.)+)}@m',
      array($this, 'markupMeme'),
      $text);
  }

  public function markupMeme(array $matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $options = array(
      'src' => null,
      'above' => null,
      'below' => null,
    );

    $parser = new PhutilSimpleOptions();
    $options = $parser->parse($matches[1]) + $options;

    $uri = id(new PhutilURI('/macro/meme/'))
      ->alter('macro', $options['src'])
      ->alter('uppertext', $options['above'])
      ->alter('lowertext', $options['below']);

    if ($this->getEngine()->isHTMLMailMode()) {
      $uri = PhabricatorEnv::getProductionURI($uri);
    }

    if ($this->getEngine()->isTextMode()) {
      $img =
        ($options['above'] != '' ? "\"{$options['above']}\"\n" : '').
        $options['src'].' <'.PhabricatorEnv::getProductionURI($uri).'>'.
        ($options['below'] != '' ? "\n\"{$options['below']}\"" : '');
    } else {
      $alt_text = pht(
        'Macro %s: %s %s',
        $options['src'],
        $options['above'],
        $options['below']);

      $img = $this->newTag(
        'img',
        array(
          'src' => $uri,
          'alt' => $alt_text,
          'class' => 'phabricator-remarkup-macro',
        ));
    }

    return $this->getEngine()->storeText($img);
  }

}
