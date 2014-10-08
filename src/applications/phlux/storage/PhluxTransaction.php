<?php

final class PhluxTransaction extends PhabricatorApplicationTransaction {

  const TYPE_EDIT_KEY     = 'phlux:key';
  const TYPE_EDIT_VALUE   = 'phlux:value';

  public function getApplicationName() {
    return 'phlux';
  }

  public function getApplicationTransactionType() {
    return PhluxVariablePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
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
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      json_encode($this->getOldValue()),
      json_encode($this->getNewValue()));
  }


}
