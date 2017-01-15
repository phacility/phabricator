<?php

final class PhabricatorImageRemarkupRule extends PhutilRemarkupRule {
  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@{(image|img) ((?:[^}\\\\]+|\\\\.)*)}@m',
      array($this, 'markupImage'),
      $text);
  }

  public function markupImage(array $matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }
    $args = array();
    $defaults = array(
      'uri' => null,
      'alt' => null,
      'href' => null,
      'width' => null,
      'height' => null,
    );
    $trimmed_match = trim($matches[2]);
    if ($this->isURI($trimmed_match)) {
      $args['uri'] = new PhutilURI($trimmed_match);
    } else {
      $parser = new PhutilSimpleOptions();
      $keys = $parser->parse($trimmed_match);

      $uri_key = '';
      foreach (array('src', 'uri', 'url') as $key) {
        if (array_key_exists($key, $keys)) {
          $uri_key = $key;
        }
      }
      if ($uri_key) {
        $args['uri'] = new PhutilURI($keys[$uri_key]);
      }
      $args += $keys;
    }

    $args += $defaults;

    if ($args['href'] && !PhabricatorEnv::isValidURIForLink($args['href'])) {
      $args['href'] = null;
    }

    if ($args['uri']) {
      $src_uri = id(new PhutilURI('/file/imageproxy/'))
        ->setQueryParam('uri', (string)$args['uri']);
      $img = $this->newTag(
        'img',
        array(
          'src' => $src_uri,
          'alt' => $args['alt'],
          'href' => $args['href'],
          'width' => $args['width'],
          'height' => $args['height'],
          ));
      return $this->getEngine()->storeText($img);
    } else {
      return $matches[0];
    }
  }

  private function isURI($uri_string) {
    // Very simple check to make sure it starts with either http or https.
    // If it does, we'll try to treat it like a valid URI
    return preg_match('~^https?\:\/\/.*\z~i', $uri_string);
  }
}
