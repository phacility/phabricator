<?php

final class PhabricatorTokenGivenFeedStory
  extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('objectPHID');
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    $phids[] = $this->getValue('objectPHID');
    $phids[] = $this->getValue('authorPHID');
    return $phids;
  }

  public function getRequiredObjectPHIDs() {
    $phids = array();
    $phids[] = $this->getValue('tokenPHID');
    return $phids;
  }

  public function renderView() {
    $view = $this->newStoryView();
    $author_phid = $this->getValue('authorPHID');
    $view->setAppIcon('fa-trophy');

    $href = $this->getHandle($this->getPrimaryObjectPHID())->getURI();
    $view->setHref($href);

    $view->setTitle($this->renderTitle());
    $view->setImage($this->getHandle($author_phid)->getImageURI());

    return $view;
  }

  private function renderTitle() {
    $token = $this->getObject($this->getValue('tokenPHID'));
    $title = pht(
      '%s awarded %s a %s token.',
      $this->linkTo($this->getValue('authorPHID')),
      $this->linkTo($this->getValue('objectPHID')),
      $token->getName());

    return $title;
  }

  public function renderText() {
    $old_target = $this->getRenderingTarget();
    $this->setRenderingTarget(PhabricatorApplicationTransaction::TARGET_TEXT);
    $title = $this->renderTitle();
    $this->setRenderingTarget($old_target);
    return $title;
  }

  public function renderAsTextForDoorkeeper(
    DoorkeeperFeedStoryPublisher $publisher) {
    // TODO: This is slightly wrong, as it does not respect implied context
    // on the publisher, so it will always say "awarded D123 a token" when it
    // should sometimes say "awarded this revision a token".
    return $this->renderText();
  }


}
