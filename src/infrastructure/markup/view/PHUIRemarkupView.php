<?php

/**
 * Simple API for rendering blocks of Remarkup.
 *
 * Example usage:
 *
 *   $fancy_text = new PHUIRemarkupView($viewer, $raw_remarkup);
 *   $view->appendChild($fancy_text);
 *
 */
final class PHUIRemarkupView extends AphrontView {

  private $corpus;
  private $markupType;
  private $contextObject;

  const DOCUMENT = 'document';

  public function __construct(PhabricatorUser $viewer, $corpus) {
    $this->setUser($viewer);
    $this->corpus = $corpus;
  }

  private function setMarkupType($type) {
    $this->markupType($type);
    return $this;
  }

  public function setContextObject($context_object) {
    $this->contextObject = $context_object;
    return $this;
  }

  public function getContextObject() {
    return $this->contextObject;
  }

  public function render() {
    $viewer = $this->getUser();
    $corpus = $this->corpus;
    $context = $this->getContextObject();

    $content = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())
        ->setPreserveLinebreaks(true)
        ->setContent($corpus),
      'default',
      $viewer,
      $context);

    if ($this->markupType == self::DOCUMENT) {
      return phutil_tag(
        'div',
        array(
          'class' => 'phabricator-remarkup phui-document-view',
        ),
        $content);
    }

    return $content;
  }

}
