<?php

final class PhortuneMerchantTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'merchant:name';

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
    }

    return parent::getTitle();
  }

}
