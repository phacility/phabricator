<?php

final class PhrictionRemarkupRule extends PhutilRemarkupRule {

  const KEY_RULE_PHRICTION_LINK = 'phriction.link';

  public function getPriority() {
    return 175.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@\B\\[\\[([^|\\]]+)(?:\\|([^\\]]+))?\\]\\]\B@U',
      array($this, 'markupDocumentLink'),
      $text);
  }

  public function markupDocumentLink(array $matches) {
    $name = trim(idx($matches, 2, ''));
    if (empty($matches[2])) {
      $name = null;
    }

    $path = trim($matches[1]);

    if (!$this->isFlatText($name)) {
      return $matches[0];
    }

    if (!$this->isFlatText($path)) {
      return $matches[0];
    }

    // If the link contains an anchor, separate that off first.
    $parts = explode('#', $path, 2);
    if (count($parts) == 2) {
      $link = $parts[0];
      $anchor = $parts[1];
    } else {
      $link = $parts[0];
      $anchor = null;
    }

    // Handle relative links.
    if ((substr($link, 0, 2) === './') || (substr($link, 0, 3) === '../')) {
      $base = $this->getRelativeBaseURI();
      if ($base !== null) {
        $base_parts = explode('/', rtrim($base, '/'));
        $rel_parts = explode('/', rtrim($link, '/'));
        foreach ($rel_parts as $part) {
          if ($part === '.') {
            // Consume standalone dots in a relative path, and do
            // nothing with them.
          } else if ($part === '..') {
            if (count($base_parts) > 0) {
              array_pop($base_parts);
            }
          } else {
            array_push($base_parts, $part);
          }
        }
        $link = implode('/', $base_parts).'/';
      }
    }

    // Link is now used for slug detection, so append a slash if one
    // is needed.
    $link = rtrim($link, '/').'/';

    $engine = $this->getEngine();
    $token = $engine->storeText('x');
    $metadata = $engine->getTextMetadata(
      self::KEY_RULE_PHRICTION_LINK,
      array());
    $metadata[] = array(
      'token' => $token,
      'link' => $link,
      'anchor' => $anchor,
      'explicitName' => $name,
    );
    $engine->setTextMetadata(self::KEY_RULE_PHRICTION_LINK, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();
    $metadata = $engine->getTextMetadata(
      self::KEY_RULE_PHRICTION_LINK,
      array());

    if (!$metadata) {
      return;
    }

    $viewer = $engine->getConfig('viewer');

    $slugs = ipull($metadata, 'link');

    $load_map = array();
    foreach ($slugs as $key => $raw_slug) {
      $lookup = PhabricatorSlug::normalize($raw_slug);
      $load_map[$lookup][] = $key;

      // Also try to lookup the slug with URL decoding applied. The right
      // way to link to a page titled "$cash" is to write "[[ $cash ]]" (and
      // not the URL encoded form "[[ %24cash ]]"), but users may reasonably
      // have copied URL encoded variations out of their browser location
      // bar or be skeptical that "[[ $cash ]]" will actually work.

      $lookup = phutil_unescape_uri_path_component($raw_slug);
      $lookup = phutil_utf8ize($lookup);
      $lookup = PhabricatorSlug::normalize($lookup);
      $load_map[$lookup][] = $key;
    }

    $visible_documents = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withSlugs(array_keys($load_map))
      ->needContent(true)
      ->execute();
    $visible_documents = mpull($visible_documents, null, 'getSlug');
    $document_map = array();
    foreach ($load_map as $lookup => $keys) {
      $visible = idx($visible_documents, $lookup);
      if (!$visible) {
        continue;
      }

      foreach ($keys as $key) {
        $document_map[$key] = array(
          'visible' => true,
          'document' => $visible,
        );
      }

      unset($load_map[$lookup]);
    }

    // For each document we found, remove all remaining requests for it from
    // the load map. If we remove all requests for a slug, remove the slug.
    // This stops us from doing unnecessary lookups on alternate names for
    // documents we already found.
    foreach ($load_map as $lookup => $keys) {
      foreach ($keys as $lookup_key => $key) {
        if (isset($document_map[$key])) {
          unset($keys[$lookup_key]);
        }
      }

      if (!$keys) {
        unset($load_map[$lookup]);
        continue;
      }

      $load_map[$lookup] = $keys;
    }


    // If we still have links we haven't found a document for, do another
    // query with the omnipotent viewer so we can distinguish between pages
    // which do not exist and pages which exist but which the viewer does not
    // have permission to see.
    if ($load_map) {
      $existent_documents = id(new PhrictionDocumentQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withSlugs(array_keys($load_map))
        ->execute();
      $existent_documents = mpull($existent_documents, null, 'getSlug');

      foreach ($load_map as $lookup => $keys) {
        $existent = idx($existent_documents, $lookup);
        if (!$existent) {
          continue;
        }

        foreach ($keys as $key) {
          $document_map[$key] = array(
            'visible' => false,
            'document' => null,
          );
        }
      }
    }

    foreach ($metadata as $key => $spec) {
      $link = $spec['link'];
      $slug = PhabricatorSlug::normalize($link);
      $name = $spec['explicitName'];
      $class = 'phriction-link';

      // If the name is something meaningful to humans, we'll render this
      // in text as: "Title" <link>. Otherwise, we'll just render: <link>.
      $is_interesting_name = (bool)strlen($name);

      $target = idx($document_map, $key, null);

      if ($target === null) {
        // The target document doesn't exist.
        if ($name === null) {
          $name = explode('/', trim($link, '/'));
          $name = end($name);
        }
        $class = 'phriction-link-missing';
      } else if (!$target['visible']) {
        // The document exists, but the user can't see it.
        if ($name === null) {
          $name = explode('/', trim($link, '/'));
          $name = end($name);
        }
        $class = 'phriction-link-lock';
      } else {
        if ($name === null) {
          // Use the title of the document if no name is set.
          $name = $target['document']
            ->getContent()
            ->getTitle();

          $is_interesting_name = true;
        }
      }

      $uri = new PhutilURI($link);
      $slug = $uri->getPath();
      $slug = PhabricatorSlug::normalize($slug);
      $slug = PhrictionDocument::getSlugURI($slug);

      $anchor = idx($spec, 'anchor');
      $href = (string)id(new PhutilURI($slug))->setFragment($anchor);

      $text_mode = $this->getEngine()->isTextMode();
      $mail_mode = $this->getEngine()->isHTMLMailMode();

      if ($this->getEngine()->getState('toc')) {
        $text = $name;
      } else if ($text_mode || $mail_mode) {
        $href = PhabricatorEnv::getProductionURI($href);
        if ($is_interesting_name) {
          $text = pht('"%s" <%s>', $name, $href);
        } else {
          $text = pht('<%s>', $href);
        }
      } else {
        if ($class === 'phriction-link-lock') {
          $name = array(
            $this->newTag(
              'i',
              array(
                'class' => 'phui-icon-view phui-font-fa fa-lock',
              ),
              ''),
            ' ',
            $name,
          );
        }
        $text = $this->newTag(
          'a',
          array(
            'href'  => $href,
            'class' => $class,
          ),
          $name);
      }

      $this->getEngine()->overwriteStoredText($spec['token'], $text);
    }
  }

  private function getRelativeBaseURI() {
    $context = $this->getEngine()->getConfig('contextObject');

    if (!$context) {
      return null;
    }

    if ($context instanceof PhrictionContent) {
      return $context->getSlug();
    }

    if ($context instanceof PhrictionDocument) {
      return $context->getContent()->getSlug();
    }

    return null;
  }


}
