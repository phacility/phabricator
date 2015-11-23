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

    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $ref = $matches[1];
    $monogram = null;
    $is_monogram = '/^U(?P<id>[1-9]\d*)/';

    if (preg_match($is_monogram, $ref, $monogram)) {
      $phurls = id(new PhabricatorPhurlURLQuery())
        ->setViewer($viewer)
        ->withIDs(array($monogram[1]))
        ->execute();
    } else if (ctype_digit($ref)) {
      $phurls = id(new PhabricatorPhurlURLQuery())
        ->setViewer($viewer)
        ->withIDs(array($ref))
        ->execute();
    } else {
      $phurls = id(new PhabricatorPhurlURLQuery())
        ->setViewer($viewer)
        ->withAliases(array($ref))
        ->execute();
    }

    $phurl = head($phurls);

    if ($phurl) {
      if ($text_mode) {
        return $phurl->getDisplayName().
          ' <'.
          $phurl->getRedirectURI().
          '>';
      }

      $link = phutil_tag(
        'a',
        array(
          'href' => $phurl->getRedirectURI(),
          'target' => '_blank',
        ),
        $phurl->getDisplayName());

      return $this->getEngine()->storeText($link);
    } else {
      return $matches[0];
    }
  }


}
