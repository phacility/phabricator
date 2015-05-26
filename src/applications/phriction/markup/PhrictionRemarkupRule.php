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
