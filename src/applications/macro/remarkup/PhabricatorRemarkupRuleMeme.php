<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleMeme
  extends PhutilRemarkupRule {

  private $images;

  public function apply($text) {
    return preg_replace_callback(
      '@{meme,((?:[^}\\\\]+|\\\\.)+)}$@m',
      array($this, 'markupMeme'),
      $text);
  }

  public function markupMeme($matches) {
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

      $img = phutil_tag(
        'img',
        array(
          'src' => (string)$uri,
          'alt' => $alt_text,
        ));
    }

    return $this->getEngine()->storeText($img);
  }

}
