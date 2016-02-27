<?php

final class AlmanacNamespaceTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'almanac:namespace:name';

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacNamespacePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this namespace.',
          $this->renderHandleLink($author_phid));
        break;
      case self::TYPE_NAME:
        return pht(
          '%s renamed this namespace from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
    }

    return parent::getTitle();
  }

}
