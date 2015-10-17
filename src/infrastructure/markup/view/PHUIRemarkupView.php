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

  public function __construct(PhabricatorUser $viewer, $corpus) {
    $this->setUser($viewer);
    $this->corpus = $corpus;
  }

  public function render() {
    $viewer = $this->getUser();
    $corpus = $this->corpus;

    return PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())
        ->setContent($corpus),
      'default',
      $viewer);
  }

}
