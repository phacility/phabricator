<?php

final class DiffusionSourceLinkRemarkupRule
  extends PhutilRemarkupRule {

  const KEY_SOURCELINKS = 'diffusion.links';

  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@{(?:src|source)\b((?:[^}\\\\]+|\\\\.)*)}@m',
      array($this, 'markupSourceLink'),
      $text);
  }

  public function markupSourceLink(array $matches) {
    $engine = $this->getEngine();
    $text_mode = $engine->isTextMode();
    $mail_mode = $engine->isHTMLMailMode();

    if (!$this->isFlatText($matches[0]) || $text_mode || $mail_mode) {
      // We could do better than this in text mode and mail mode, but focus
      // on web mode first.
      return $matches[0];
    }

    $metadata_key = self::KEY_SOURCELINKS;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    $token = $engine->storeText($matches[0]);

    $metadata[] = array(
      'token' => $token,
      'raw' => $matches[0],
      'input' => $matches[1],
    );

    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();
    $metadata_key = self::KEY_SOURCELINKS;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    if (!$metadata) {
      return;
    }

    $viewer = $engine->getConfig('viewer');
    if (!$viewer) {
      return;
    }

    $defaults = array(
      'repository' => null,
      'line' => null,
      'commit' => null,
      'ref' => null,
    );

    $tags = array();
    foreach ($metadata as $ref) {
      $token = $ref['token'];
      $raw = $ref['raw'];
      $input = $ref['input'];

      $pattern =
        '(^'.
        '[\s,]*'.
        '(?:"(?P<quotedpath>(?:[^\\\\"]+|\\.)+)"|(?P<rawpath>[^\s,]+))'.
        '[\s,]*'.
        '(?P<options>.*)'.
        '\z)';
      $matches = null;
      if (!preg_match($pattern, $input, $matches)) {
        $hint_text = pht(
          'Missing path, expected "{src path ...}" in: %s',
          $raw);
        $hint = $this->newSyntaxHint($hint_text);

        $engine->overwriteStoredText($token, $hint);
        continue;
      }

      $path = idx($matches, 'rawpath');
      if (!strlen($path)) {
        $path = idx($matches, 'quotedpath');
        $path = stripcslashes($path);
      }

      $parts = explode(':', $path, 2);
      if (count($parts) == 2) {
        $repository = nonempty($parts[0], null);
        $path = $parts[1];
      } else {
        $repository = null;
        $path = $parts[0];
      }

      $options = $matches['options'];

      $parser = new PhutilSimpleOptions();
      $options = $parser->parse($options) + $defaults;

      foreach ($options as $key => $value) {
        if (!array_key_exists($key, $defaults)) {
          $hint_text = pht(
            'Unknown option "%s" in: %s',
            $key,
            $raw);
          $hint = $this->newSyntaxHint($hint_text);

          $engine->overwriteStoredText($token, $hint);
          continue 2;
        }
      }

      if ($options['repository'] !== null) {
        $repository = $options['repository'];
      }

      if ($repository === null) {
        $hint_text = pht(
          'Missing repository, expected "{src repository:path ...}" '.
          'or "{src path repository=...}" in: %s',
          $raw);
        $hint = $this->newSyntaxHint($hint_text);

        $engine->overwriteStoredText($token, $hint);
        continue;
      }

      $tags[] = array(
        'token' => $token,
        'raw' => $raw,
        'identifier' => $repository,
        'path' => $path,
        'options' => $options,
      );
    }

    if (!$tags) {
      return;
    }

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIdentifiers(ipull($tags, 'identifier'));

    $query->execute();

    $repository_map = $query->getIdentifierMap();

    foreach ($tags as $tag) {
      $token = $tag['token'];

      $identifier = $tag['identifier'];
      $repository = idx($repository_map, $identifier);
      if (!$repository) {
        // For now, just bail out here. Ideally, we should distingiush between
        // restricted and invalid repositories.
        continue;
      }

      $drequest = DiffusionRequest::newFromDictionary(
        array(
          'user' => $viewer,
          'repository' => $repository,
        ));

      $options = $tag['options'];

      $line = $options['line'];
      $commit = $options['commit'];
      $ref_name = $options['ref'];

      $link_uri = $drequest->generateURI(
        array(
          'action' => 'browse',
          'path' => $tag['path'],
          'commit' => $commit,
          'line' => $line,
          'branch' => $ref_name,
        ));

      $view = id(new DiffusionSourceLinkView())
        ->setRepository($repository)
        ->setPath($tag['path'])
        ->setURI($link_uri);

      if ($line !== null) {
        $view->setLine($line);
      }

      if ($commit !== null) {
        $view->setCommit($commit);
      }

      if ($ref_name !== null) {
        $view->setRefName($ref_name);
      }

      $engine->overwriteStoredText($token, $view);
    }
  }

  private function newSyntaxHint($text) {
    return id(new PHUITagView())
      ->setType(PHUITagView::TYPE_SHADE)
      ->setColor('red')
      ->setIcon('fa-exclamation-triangle')
      ->setName($text);
  }

}
