<?php

/**
 * @group legalpad
 */
final class LegalpadTransaction extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'legalpad';
  }

  public function getApplicationTransactionType() {
    return PhabricatorLegalpadPHIDTypeDocument::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new LegalpadTransactionComment();
  }

  public function getApplicationTransactionViewObject() {
    return new LegalpadTransactionView();
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_TEXT:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case LegalpadTransactionType::TYPE_TITLE:
        return pht(
          '%s renamed this document from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
        break;
      case LegalpadTransactionType::TYPE_TEXT:
        return pht(
          "%s updated the document's text.",
          $this->renderHandleLink($author_phid));
        break;
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case LegalpadTransactionType::TYPE_TITLE:
      case LegalpadTransactionType::TYPE_TEXT:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
  }

}
