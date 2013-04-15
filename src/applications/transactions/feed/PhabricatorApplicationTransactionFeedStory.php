<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionFeedStory
  extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('objectPHID');
  }

  public function getRequiredObjectPHIDs() {
    return array(
      $this->getPrimaryTransactionPHID(),
    );
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    $phids[] = array($this->getValue('objectPHID'));
    $phids[] = $this->getPrimaryTransaction()->getRequiredHandlePHIDs();
    return array_mergev($phids);
  }

  protected function getPrimaryTransactionPHID() {
    return head($this->getValue('transactionPHIDs'));
  }

  protected function getPrimaryTransaction() {
    return $this->getObject($this->getPrimaryTransactionPHID());
  }

  public function renderView() {
    $view = new PHUIFeedStoryView();
    $view->setViewed($this->getHasViewed());

    $href = $this->getHandle($this->getPrimaryObjectPHID())->getURI();
    $view->setHref($view);

    $xaction_phids = $this->getValue('transactionPHIDs');
    $xaction = $this->getObject(head($xaction_phids));

    $xaction->setHandles($this->getHandles());
    $view->setTitle($xaction->getTitleForFeed());

    return $view;
  }

  public function renderText() {
    // TODO: This is grotesque; the feed notification handler relies on it.
    return strip_tags($this->renderView()->render());
  }

}
