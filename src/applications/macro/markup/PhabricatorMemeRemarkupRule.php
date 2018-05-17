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

    $engine = id(new PhabricatorMemeEngine())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->setTemplate($options['src'])
      ->setAboveText($options['above'])
      ->setBelowText($options['below']);

    $asset = $engine->loadCachedFile();

    $is_html_mail = $this->getEngine()->isHTMLMailMode();
    $is_text = $this->getEngine()->isTextMode();
    $must_inline = ($is_html_mail || $is_text);

    if ($must_inline) {
      if (!$asset) {
        try {
          $asset = $engine->newAsset();
        } catch (Exception $ex) {
          return $matches[0];
        }
      }
    }

    if ($asset) {
      $uri = $asset->getViewURI();
    } else {
      $uri = $engine->getGenerateURI();
    }

    if ($is_text) {
      $parts = array();

      $above = $options['above'];
      if (strlen($above)) {
        $parts[] = pht('"%s"', $above);
      }

      $parts[] = $options['src'].' <'.$uri.'>';

      $below = $options['below'];
      if (strlen($below)) {
        $parts[] = pht('"%s"', $below);
      }

      $parts = implode("\n", $parts);
      return $this->getEngine()->storeText($parts);
    }

    $alt_text = pht(
      'Macro %s: %s %s',
      $options['src'],
      $options['above'],
      $options['below']);

    if ($asset) {
      $img = $this->newTag(
        'img',
        array(
          'src' => $uri,
          'class' => 'phabricator-remarkup-macro',
          'alt' => $alt_text,
        ));
    } else {
      $img = id(new PHUIRemarkupImageView())
        ->setURI($uri)
        ->addClass('phabricator-remarkup-macro')
        ->setAlt($alt_text);
    }

    return $this->getEngine()->storeText($img);
  }

}
