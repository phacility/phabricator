<?php

final class AlmanacBindingTransaction
  extends AlmanacTransaction {

  const TYPE_INTERFACE = 'almanac:binding:interface';
  const TYPE_DISABLE = 'almanac:binding:disable';

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacBindingPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INTERFACE:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
    }

    return $phids;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INTERFACE:
        if ($old === null) {
          return pht(
            '%s created this binding.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed this binding from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
        break;
      case self::TYPE_DISABLE:
        if ($new) {
          return pht(
            '%s disabled this binding.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s enabled this binding.',
            $this->renderHandleLink($author_phid));
        }
        break;
    }

    return parent::getTitle();
  }

}
