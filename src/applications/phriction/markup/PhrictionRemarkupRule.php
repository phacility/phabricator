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
    $link = trim($matches[1]);

    // Handle relative links.
    if ((substr($link, 0, 2) === './') || (substr($link, 0, 3) === '../')) {
      $base = null;
      $context = $this->getEngine()->getConfig('contextObject');
      if ($context !== null && $context instanceof PhrictionContent) {
        // Handle content when it's being rendered in document view.
        $base = $context->getSlug();
      }
      if ($context !== null && is_array($context) &&
          idx($context, 'phriction.isPreview')) {
        // Handle content when it's a preview for the Phriction editor.
        $base = idx($context, 'phriction.slug');
      }
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

    $name = trim(idx($matches, 2, ''));
    if (empty($matches[2])) {
      $name = null;
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

    $slugs = ipull($metadata, 'link');

    // We have to make two queries here to distinguish between
    // documents the user can't see, and documents that don't
    // exist.
    $visible_documents = id(new PhrictionDocumentQuery())
      ->setViewer($engine->getConfig('viewer'))
      ->withSlugs($slugs)
      ->needContent(true)
      ->execute();
    $existant_documents = id(new PhrictionDocumentQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withSlugs($slugs)
      ->execute();

    $visible_documents = mpull($visible_documents, null, 'getSlug');
    $existant_documents = mpull($existant_documents, null, 'getSlug');

    foreach ($metadata as $spec) {
      $link = $spec['link'];
      $slug = PhabricatorSlug::normalize($link);
      $name = $spec['explicitName'];
      $class = 'phriction-link';

      // If the name is something meaningful to humans, we'll render this
      // in text as: "Title" <link>. Otherwise, we'll just render: <link>.
      $is_interesting_name = (bool)strlen($name);

      if (idx($existant_documents, $slug) === null) {
        // The target document doesn't exist.
        if ($name === null) {
          $name = explode('/', trim($slug, '/'));
          $name = end($name);
        }
        $class = 'phriction-link-missing';
      } else if (idx($visible_documents, $slug) === null) {
        // The document exists, but the user can't see it.
        if ($name === null) {
          $name = explode('/', trim($slug, '/'));
          $name = end($name);
        }
        $class = 'phriction-link-lock';
      } else {
        if ($name === null) {
          // Use the title of the document if no name is set.
          $name = $visible_documents[$slug]
            ->getContent()
            ->getTitle();

          $is_interesting_name = true;
        }
      }

      $uri      = new PhutilURI($link);
      $slug     = $uri->getPath();
      $fragment = $uri->getFragment();
      $slug     = PhabricatorSlug::normalize($slug);
      $slug     = PhrictionDocument::getSlugURI($slug);
      $href     = (string)id(new PhutilURI($slug))->setFragment($fragment);

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

}
