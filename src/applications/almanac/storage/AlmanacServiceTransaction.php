<?php

final class AlmanacServiceTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'almanac:service:name';

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacServicePHIDType::TYPECONST;
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
    }

    return parent::getTitle();
  }

}
