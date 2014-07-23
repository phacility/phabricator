<?php

final class NuanceSourceTransaction
  extends NuanceTransaction {

  const TYPE_NAME   = 'name-source';

  public function getApplicationTransactionType() {
    return NuanceSourcePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceSourceTransactionComment();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_phid = $this->getAuthorPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this source.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this source from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
    }

  }

}
