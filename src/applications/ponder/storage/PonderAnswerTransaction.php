<?php

final class PonderAnswerTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CONTENT = 'ponder.answer:content';

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_answertransaction';
  }

  public function getApplicationTransactionType() {
    return PonderPHIDTypeAnswer::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderAnswerTransactionComment();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        // TODO: This is not so good.
        return pht(
          '%s edited their answer to %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return $this->getTitle();
  }

}

