<?php

final class LegalpadTransaction extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'title';
  const TYPE_TEXT = 'text';
  const TYPE_SIGNATURE_TYPE = 'legalpad:signature-type';
  const TYPE_PREAMBLE = 'legalpad:premable';
  const TYPE_REQUIRE_SIGNATURE = 'legalpad:require-signature';

  public function getApplicationName() {
    return 'legalpad';
  }

  public function getApplicationTransactionType() {
    return PhabricatorLegalpadDocumentPHIDType::TYPECONST;
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
      case self::TYPE_TITLE:
      case self::TYPE_TEXT:
        return ($old === null);
      case self::TYPE_SIGNATURE_TYPE:
        return true;
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_TITLE:
        return pht(
          '%s renamed this document from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_TEXT:
        return pht(
          "%s updated the document's text.",
          $this->renderHandleLink($author_phid));
      case self::TYPE_PREAMBLE:
        return pht(
          '%s updated the preamble.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_REQUIRE_SIGNATURE:
        if ($new) {
          $text = pht(
            '%s set the document to require signatures.',
            $this->renderHandleLink($author_phid));
        } else {
          $text = pht(
            '%s set the document to not require signatures.',
            $this->renderHandleLink($author_phid));
        }
        return $text;
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_TEXT:
      case self::TYPE_PREAMBLE:
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
