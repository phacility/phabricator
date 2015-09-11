<?php

final class NuanceQueueTransaction extends NuanceTransaction {

  const TYPE_NAME = 'nuance.queue.name';

  public function getApplicationTransactionType() {
    return NuanceQueuePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceQueueTransactionComment();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $type = $this->getTransactionType();

    switch ($type) {
      case self::TYPE_NAME:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $type = $this->getTransactionType();

    $author_phid = $this->getAuthorPHID();

    switch ($type) {
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
