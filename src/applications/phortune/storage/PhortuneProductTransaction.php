<?php

final class PhortuneProductTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME   = 'product:name';
  const TYPE_PRICE  = 'product:price';

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_PDCT;
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
            '%s created this product.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this product from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_PRICE:
        if ($old === null) {
          return pht(
            '%s set product price to %s.',
            $this->renderHandleLink($author_phid),
            PhortuneCurrency::newFromString($new)
              ->formatForDisplay());
        } else {
          return pht(
            '%s changed product price from %s to %s.',
            $this->renderHandleLink($author_phid),
            PhortuneCurrency::newFromString($old)
              ->formatForDisplay(),
            PhortuneCurrency::newFromString($new)
              ->formatForDisplay());
        }
        break;
    }

    return parent::getTitle();
  }

}
