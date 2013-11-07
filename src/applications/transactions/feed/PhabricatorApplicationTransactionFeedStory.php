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
    return $this->getValue('transactionPHIDs');
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    $phids[] = $this->getValue('objectPHID');
    foreach ($this->getValue('transactionPHIDs') as $xaction_phid) {
      $xaction = $this->getObject($xaction_phid);
      foreach ($xaction->getRequiredHandlePHIDs() as $handle_phid) {
        $phids[] = $handle_phid;
      }
    }
    return $phids;
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
    $xaction = $this->getPrimaryTransaction();

    $xaction->setHandles($this->getHandles());
    $view->setTitle($xaction->getTitleForFeed($this));

    foreach ($xaction_phids as $xaction_phid) {
      $secondary_xaction = $this->getObject($xaction_phid);
      $secondary_xaction->setHandles($this->getHandles());

      $body = $secondary_xaction->getBodyForFeed($this);
      if (nonempty($body)) {
        $view->appendChild($body);
      }
    }

    $view->setImage(
      $this->getHandle($xaction->getAuthorPHID())->getImageURI());

    return $view;
  }

  public function renderText() {
    // TODO: This is grotesque; the feed notification handler relies on it.
    return strip_tags(hsprintf('%s', $this->renderView()->render()));
  }

}
