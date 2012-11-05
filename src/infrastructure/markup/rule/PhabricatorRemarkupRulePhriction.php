<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRulePhriction
  extends PhutilRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '@\B\\[\\[([^|\\]]+)(?:\\|([^\\]]+))?\\]\\]\B@U',
      array($this, 'markupDocumentLink'),
      $text);
  }

  public function markupDocumentLink($matches) {

    $slug = trim($matches[1]);
    $name = trim(idx($matches, 2, $slug));
    $name = explode('/', trim($name, '/'));
    $name = end($name);

    $slug = PhabricatorSlug::normalize($slug);
    $uri  = PhrictionDocument::getSlugURI($slug);

    return $this->getEngine()->storeText(
      phutil_render_tag(
        'a',
        array(
          'href'  => $uri,
          'class' => 'phriction-link',
        ),
        phutil_escape_html($name)));
  }

}
