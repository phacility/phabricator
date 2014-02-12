<?php

final class DifferentialTransaction extends PhabricatorApplicationTransaction {

  const TYPE_INLINE = 'differential:inline';
  const TYPE_UPDATE = 'differential:update';
  const TYPE_ACTION = 'differential:action';

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return DifferentialPHIDTypeRevision::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new DifferentialTransactionComment();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return pht(
          '%s added inline comments.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

}
