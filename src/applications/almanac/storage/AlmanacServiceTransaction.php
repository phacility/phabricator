<?php

final class AlmanacServiceTransaction
  extends AlmanacTransaction {

  const TYPE_NAME = 'almanac:service:name';
  const TYPE_LOCK = 'almanac:service:lock';

  public function getApplicationTransactionType() {
    return AlmanacServicePHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this service.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this service from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_LOCK:
        if ($new) {
          return pht(
            '%s locked this service.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s unlocked this service.',
            $this->renderHandleLink($author_phid));
        }
        break;
    }

    return parent::getTitle();
  }

}
