<?php

final class PhabricatorImageRemarkupRule extends PhutilRemarkupRule {

  const KEY_RULE_EXTERNAL_IMAGE = 'rule.external-image';

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
      'width' => null,
      'height' => null,
    );

    $trimmed_match = trim($matches[2]);
    if ($this->isURI($trimmed_match)) {
      $args['uri'] = $trimmed_match;
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
        $args['uri'] = $keys[$uri_key];
      }
      $args += $keys;
    }

    $args += $defaults;

    $uri_arg = $args['uri'];
    if ($uri_arg === null || !strlen($uri_arg)) {
      return $matches[0];
    }

    // Make sure this is something that looks roughly like a real URI. We'll
    // validate it more carefully before proxying it, but if whatever the user
    // has typed isn't even close, just decline to activate the rule behavior.
    try {
      $uri = new PhutilURI($uri_arg);

      if ($uri->getProtocol() === null || !strlen($uri->getProtocol())) {
        return $matches[0];
      }

      $args['uri'] = (string)$uri;
    } catch (Exception $ex) {
      return $matches[0];
    }

    $engine = $this->getEngine();
    $metadata_key = self::KEY_RULE_EXTERNAL_IMAGE;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    $token = $engine->storeText('<img>');

    $metadata[] = array(
      'token' => $token,
      'args' => $args,
    );

    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();
    $metadata_key = self::KEY_RULE_EXTERNAL_IMAGE;
    $images = $engine->getTextMetadata($metadata_key, array());
    $engine->setTextMetadata($metadata_key, array());

    if (!$images) {
      return;
    }

    // Look for images we've already successfully fetched that aren't about
    // to get eaten by the GC. For any we find, we can just emit a normal
    // "<img />" tag pointing directly to the file.

    // For files which we don't hit in the cache, we emit a placeholder
    // instead and use AJAX to actually perform the fetch.

    $digests = array();
    foreach ($images as $image) {
      $uri = $image['args']['uri'];
      $digests[] = PhabricatorHash::digestForIndex($uri);
    }

    $caches = id(new PhabricatorFileExternalRequest())->loadAllWhere(
      'uriIndex IN (%Ls) AND isSuccessful = 1 AND ttl > %d',
      $digests,
      PhabricatorTime::getNow() + phutil_units('1 hour in seconds'));

    $file_phids = array();
    foreach ($caches as $cache) {
      $file_phids[$cache->getFilePHID()] = $cache->getURI();
    }

    $file_map = array();
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs(array_keys($file_phids))
        ->execute();
      foreach ($files as $file) {
        $phid = $file->getPHID();

        $file_remote_uri = $file_phids[$phid];
        $file_view_uri = $file->getViewURI();

        $file_map[$file_remote_uri] = $file_view_uri;
      }
    }

    foreach ($images as $image) {
      $args = $image['args'];
      $uri = $args['uri'];

      $direct_uri = idx($file_map, $uri);
      if ($direct_uri) {
        $img = phutil_tag(
          'img',
          array(
            'src' => $direct_uri,
            'alt' => $args['alt'],
            'width' => $args['width'],
            'height' => $args['height'],
          ));
      } else {
        $src_uri = id(new PhutilURI('/file/imageproxy/'))
          ->replaceQueryParam('uri', $uri);

        $img = id(new PHUIRemarkupImageView())
          ->setURI($src_uri)
          ->setAlt($args['alt'])
          ->setWidth($args['width'])
          ->setHeight($args['height']);
      }

      $engine->overwriteStoredText($image['token'], $img);
    }
  }

  private function isURI($uri_string) {
    // Very simple check to make sure it starts with either http or https.
    // If it does, we'll try to treat it like a valid URI
    return preg_match('~^https?\:\/\/.*\z~i', $uri_string);
  }

}
