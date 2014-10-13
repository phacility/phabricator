<?php

final class PhortuneCartTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CREATED = 'cart:created';
  const TYPE_HOLD = 'cart:hold';
  const TYPE_REVIEW = 'cart:review';
  const TYPE_CANCEL = 'cart:cancel';
  const TYPE_REFUND = 'cart:refund';
  const TYPE_PURCHASED = 'cart:purchased';

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortuneCartPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case self::TYPE_CREATED:
        return true;
    }

    return parent::shouldHideForMail($xactions);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATED:
        return pht('This order was created.');
      case self::TYPE_HOLD:
        return pht('This order was put on hold until payment clears.');
      case self::TYPE_REVIEW:
        return pht(
          'This order was flagged for manual processing by the merchant.');
      case self::TYPE_CANCEL:
        return pht('This order was cancelled.');
      case self::TYPE_REFUND:
        return pht('This order was refunded.');
      case self::TYPE_PURCHASED:
        return pht('Payment for this order was completed.');
    }

    return parent::getTitle();
  }

}
