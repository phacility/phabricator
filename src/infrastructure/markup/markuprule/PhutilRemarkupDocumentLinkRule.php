<?php

final class PhutilRemarkupDocumentLinkRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 150.0;
  }

  public function apply($text) {
    // Handle mediawiki-style links: [[ href | name ]]
    $text = preg_replace_callback(
      '@\B\\[\\[([^|\\]]+)(?:\\|([^\\]]+))?\\]\\]\B@U',
      array($this, 'markupDocumentLink'),
      $text);

    // Handle markdown-style links: [name](href)
    $text = preg_replace_callback(
      '@'.
        '\B'.
        '\\[([^\\]]+)\\]'.
        '\\('.
          '(\s*'.
            // See T12343. This is making some kind of effort to implement
            // parenthesis balancing rules. It won't get nested parentheses
            // right, but should do OK for Wikipedia pages, which seem to be
            // the most important use case.

            // Match zero or more non-parenthesis, non-space characters.
            '[^\s()]*'.
            // Match zero or more sequences of "(...)", where two balanced
            // parentheses enclose zero or more normal characters. If we
            // match some, optionally match more stuff at the end.
            '(?:(?:\\([^ ()]*\\))+[^\s()]*)?'.
          '\s*)'.
        '\\)'.
      '\B'.
      '@U',
      array($this, 'markupAlternateLink'),
      $text);

    return $text;
  }

  protected function renderHyperlink($link, $name) {
    $engine = $this->getEngine();

    $is_anchor = false;
    if (strncmp($link, '/', 1) == 0) {
      $base = phutil_string_cast($engine->getConfig('uri.base'));
      $base = rtrim($base, '/');
      $link = $base.$link;
    } else if (strncmp($link, '#', 1) == 0) {
      $here = $engine->getConfig('uri.here');
      $link = $here.$link;

      $is_anchor = true;
    }

    if ($engine->isTextMode()) {
      // If present, strip off "mailto:" or "tel:".
      $link = preg_replace('/^(?:mailto|tel):/', '', $link);

      if (!strlen($name)) {
        return $link;
      }

      return $name.' <'.$link.'>';
    }

    if (!strlen($name)) {
      $name = $link;
      $name = preg_replace('/^(?:mailto|tel):/', '', $name);
    }

    if ($engine->getState('toc')) {
      return $name;
    }

    $same_window = $engine->getConfig('uri.same-window', false);
    if ($same_window) {
      $target = null;
    } else {
      $target = '_blank';
    }

    // For anchors on the same page, always stay here.
    if ($is_anchor) {
      $target = null;
    }

    return phutil_tag(
      'a',
      array(
        'href' => $link,
        'class' => 'remarkup-link',
        'target' => $target,
        'rel' => 'noreferrer',
      ),
      $name);
  }

  public function markupAlternateLink(array $matches) {
    $uri = trim($matches[2]);

    if (!strlen($uri)) {
      return $matches[0];
    }

    // NOTE: We apply some special rules to avoid false positives here. The
    // major concern is that we do not want to convert `x[0][1](y)` in a
    // discussion about C source code into a link. To this end, we:
    //
    //   - Don't match at word boundaries;
    //   - require the URI to contain a "/" character or "@" character; and
    //   - reject URIs which being with a quote character.

    if ($uri[0] == '"' || $uri[0] == "'" || $uri[0] == '`') {
      return $matches[0];
    }

    if (strpos($uri, '/') === false &&
        strpos($uri, '@') === false &&
        strncmp($uri, 'tel:', 4)) {
      return $matches[0];
    }

    return $this->markupDocumentLink(
      array(
        $matches[0],
        $matches[2],
        $matches[1],
      ));
  }

  public function markupDocumentLink(array $matches) {
    $uri = trim($matches[1]);
    $name = trim(idx($matches, 2, ''));

    if (!$this->isFlatText($uri)) {
      return $matches[0];
    }

    if (!$this->isFlatText($name)) {
      return $matches[0];
    }

    // If whatever is being linked to begins with "/" or "#", or has "://",
    // or is "mailto:" or "tel:", treat it as a URI instead of a wiki page.
    $is_uri = preg_match('@(^/)|(://)|(^#)|(^(?:mailto|tel):)@', $uri);

    if ($is_uri && strncmp('/', $uri, 1) && strncmp('#', $uri, 1)) {
      $protocols = $this->getEngine()->getConfig(
        'uri.allowed-protocols',
        array());

      try {
        $protocol = id(new PhutilURI($uri))->getProtocol();
        if (!idx($protocols, $protocol)) {
          // Don't treat this as a URI if it's not an allowed protocol.
          $is_uri = false;
        }
      } catch (Exception $ex) {
        // We can end up here if we try to parse an ambiguous URI, see
        // T12796.
        $is_uri = false;
      }
    }

    // As a special case, skip "[[ / ]]" so that Phriction picks it up as a
    // link to the Phriction root. It is more useful to be able to use this
    // syntax to link to the root document than the home page of the install.
    if ($uri == '/') {
      $is_uri = false;
    }

    if (!$is_uri) {
      return $matches[0];
    }

    return $this->getEngine()->storeText($this->renderHyperlink($uri, $name));
  }

}
