<?php

final class PhortunePaymentProviderConfigTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CREATE = 'paymentprovider:create';
  const TYPE_PROPERTY = 'paymentprovider:property';
  const TYPE_ENABLE = 'paymentprovider:enable';

  const PROPERTY_KEY = 'provider-property';

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortunePaymentProviderPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return pht(
          '%s created this payment provider.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ENABLE:
        if ($new) {
          return pht(
            '%s enabled this payment provider.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s disabled this payment provider.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_PROPERTY:
        // TODO: Allow providers to improve this.

        return pht(
          '%s edited a property of this payment provider.',
          $this->renderHandleLink($author_phid));
        break;
    }

    return parent::getTitle();
  }

}
