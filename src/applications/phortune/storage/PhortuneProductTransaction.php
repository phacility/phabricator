<?php

final class PhortuneProductTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME   = 'product:name';
  const TYPE_TYPE   = 'product:type';
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

  public function getApplicationObjectTypeName() {
    return pht('product');
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
            PhortuneUtil::formatCurrency($new));
        } else {
          return pht(
            '%s changed product price from %s to %s.',
            $this->renderHandleLink($author_phid),
            PhortuneUtil::formatCurrency($old),
            PhortuneUtil::formatCurrency($new));
        }
        break;
      case self::TYPE_TYPE:
        $map = PhortuneProduct::getTypeMap();
        if ($old === null) {
          return pht(
            '%s set product type to "%s".',
            $this->renderHandleLink($author_phid),
            $map[$new]);
        } else {
          return pht(
            '%s changed product type from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $map[$old],
            $map[$new]);
        }
        break;
    }

    return parent::getTitle();
  }

}
