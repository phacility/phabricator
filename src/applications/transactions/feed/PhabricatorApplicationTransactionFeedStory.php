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
    $view = $this->newStoryView();

    $handle = $this->getHandle($this->getPrimaryObjectPHID());
    $view->setHref($handle->getURI());

    $view->setAppIconFromPHID($handle->getPHID());

    $xaction_phids = $this->getValue('transactionPHIDs');
    $xaction = $this->getObject(head($xaction_phids));

    $xaction->setHandles($this->getHandles());
    $view->setTitle($xaction->getTitleForFeed());
    $body = $xaction->getBodyForFeed($this);
    if (nonempty($body)) {
      $view->appendChild($body);
    }

    $view->setImage(
      $this->getHandle(
        $this->getPrimaryTransaction()->getAuthorPHID())->getImageURI());

    return $view;
  }

  public function renderText() {
    // TODO: This is grotesque; the feed notification handler relies on it.
    return strip_tags(hsprintf('%s', $this->renderView()->render()));
  }

}
