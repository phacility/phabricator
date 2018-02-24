<?php

final class PhabricatorPhurlLinkRemarkupRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    // `((123))` remarkup link to `/u/123`
    // `((alias))` remarkup link to `/u/alias`
    return preg_replace_callback(
      '/\(\(([^ )]+)\)\)/',
      array($this, 'markupLink'),
      $text);
  }

  public function markupLink(array $matches) {
    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    $text_mode = $engine->isTextMode();
    $html_mode = $engine->isHTMLMailMode();

    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $ref = $matches[1];
    $monogram = null;
    $is_monogram = '/^U(?P<id>[1-9]\d*)/';

    $query = id(new PhabricatorPhurlURLQuery())
      ->setViewer($viewer);

    if (preg_match($is_monogram, $ref, $monogram)) {
      $query->withIDs(array($monogram[1]));
    } else if (ctype_digit($ref)) {
      $query->withIDs(array($ref));
    } else {
      $query->withAliases(array($ref));
    }

    $phurl = $query->executeOne();
    if (!$phurl) {
      return $matches[0];
    }

    $uri = $phurl->getRedirectURI();
    $name = $phurl->getDisplayName();

    if ($text_mode || $html_mode) {
      $uri = PhabricatorEnv::getProductionURI($uri);
    }

    if ($text_mode) {
      return pht(
        '%s <%s>',
        $name,
        $uri);
    } else {
      $link = phutil_tag(
        'a',
        array(
          'href' => $uri,
          'target' => '_blank',
          'rel' => 'noreferrer',
        ),
        $name);
    }

    return $this->getEngine()->storeText($link);
  }

}
