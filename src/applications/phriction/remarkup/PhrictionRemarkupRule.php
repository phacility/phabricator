<?php

/**
 * @group markup
 */
final class PhrictionRemarkupRule
  extends PhutilRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '@\B\\[\\[([^|\\]]+)(?:\\|([^\\]]+))?\\]\\]\B@U',
      array($this, 'markupDocumentLink'),
      $text);
  }

  public function markupDocumentLink($matches) {

    $link = trim($matches[1]);
    $name = trim(idx($matches, 2, $link));
    $name = explode('/', trim($name, '/'));
    $name = end($name);

    $uri      = new PhutilURI($link);
    $slug     = $uri->getPath();
    $fragment = $uri->getFragment();
    $slug     = PhabricatorSlug::normalize($slug);
    $slug     = PhrictionDocument::getSlugURI($slug);
    $href     = (string) id(new PhutilURI($slug))->setFragment($fragment);

    if ($this->getEngine()->getState('toc')) {
      $text = $name;
    } else if ($this->getEngine()->isTextMode()) {
      return PhabricatorEnv::getProductionURI($href);
    } else {
      $text = phutil_tag(
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
