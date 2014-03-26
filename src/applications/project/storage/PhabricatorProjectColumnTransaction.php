<?php

final class PhabricatorProjectColumnTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME       = 'project:col:name';
  const TYPE_STATUS     = 'project:col:status';

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectPHIDTypeColumn::TYPECONST;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    switch ($this->getTransactionType()) {
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
        if (!strlen($old)) {
          return pht(
            '%s created this column.',
            $author_handle);
        } else {
          return pht(
            '%s renamed this column from "%s" to "%s".',
            $author_handle,
            $old,
            $new);
        }
      case PhabricatorProjectColumnTransaction::TYPE_STATUS:
        switch ($new) {
          case PhabricatorProjectColumn::STATUS_ACTIVE:
            return pht(
              '%s activated this column.',
              $author_handle);
          case PhabricatorProjectColumn::STATUS_DELETED:
            return pht(
              '%s deleted this column.',
              $author_handle);
        }
        break;
    }

    return parent::getTitle();
  }

}
