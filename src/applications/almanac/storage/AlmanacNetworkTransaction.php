<?php

final class AlmanacNetworkTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'almanac:network:name';

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacNetworkPHIDType::TYPECONST;
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
            '%s created this network.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this network from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
    }

    return parent::getTitle();
  }

}
