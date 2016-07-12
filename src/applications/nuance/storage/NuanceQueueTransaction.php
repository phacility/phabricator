<?php

final class NuanceQueueTransaction extends NuanceTransaction {

  const TYPE_NAME = 'nuance.queue.name';

  public function getApplicationTransactionType() {
    return NuanceQueuePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceQueueTransactionComment();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $type = $this->getTransactionType();

    $author_phid = $this->getAuthorPHID();

    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this queue.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_NAME:
        return pht(
          '%s renamed this queue from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
    }

    return parent::getTitle();
  }
}
