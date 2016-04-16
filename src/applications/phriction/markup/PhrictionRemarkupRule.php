<?php

final class PhrictionRemarkupRule extends PhutilRemarkupRule {

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
    if (substr($link, 0, 2) === './') {
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
        $rel_parts = explode('/', substr(rtrim($link, '/'), 2));
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

    $name = trim(idx($matches, 2, $link));
    if (empty($matches[2])) {
      $name = explode('/', trim($name, '/'));
      $name = end($name);
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
      return PhabricatorEnv::getProductionURI($href);
    } else {
      $text = $this->newTag(
        'a',
        array(
          'href'  => $href,
          'class' => 'phriction-link',
        ),
        $name);
    }

    return $this->getEngine()->storeText($text);
  }

}
