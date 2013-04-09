<?php

final class PhluxTransaction extends PhabricatorApplicationTransaction {

  const TYPE_EDIT_KEY     = 'phlux:key';
  const TYPE_EDIT_VALUE   = 'phlux:value';

  public function getApplicationName() {
    return 'phlux';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_PVAR;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationObjectTypeName() {
    return pht('variable');
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT_KEY:
        return pht(
          '%s created this variable.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_EDIT_VALUE:
        return pht(
          '%s updated this variable.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_EDIT_VALUE:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText(json_encode($old))
      ->setNewText(json_encode($new));

    return $view->render();
  }


}
