<?php

final class PhortuneMerchantTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'merchant:name';
  const TYPE_DESCRIPTION = 'merchant:description';
  const TYPE_CONTACTINFO = 'merchant:contactinfo';
  const TYPE_INVOICEEMAIL = 'merchant:invoiceemail';
  const TYPE_INVOICEFOOTER = 'merchant:invoicefooter';
  const TYPE_PICTURE = 'merchant:picture';

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortuneMerchantPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this merchant.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this merchant from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for this merchant.',
            $this->renderHandleLink($author_phid));
      case self::TYPE_CONTACTINFO:
        return pht(
          '%s updated the contact information for this merchant.',
            $this->renderHandleLink($author_phid));
      case self::TYPE_INVOICEEMAIL:
        return pht(
          '%s updated the invoice email for this merchant.',
            $this->renderHandleLink($author_phid));
      case self::TYPE_INVOICEFOOTER:
        return pht(
          '%s updated the invoice footer for this merchant.',
            $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
      case self::TYPE_CONTACTINFO:
      case self::TYPE_INVOICEEMAIL:
      case self::TYPE_INVOICEFOOTER:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($this->getOldValue() !== null);
      case self::TYPE_CONTACTINFO:
        return ($this->getOldValue() !== null);
      case self::TYPE_INVOICEEMAIL:
        return ($this->getOldValue() !== null);
      case self::TYPE_INVOICEFOOTER:
        return ($this->getOldValue() !== null);
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
