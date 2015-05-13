<?php

final class PhabricatorProjectColumnTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME       = 'project:col:name';
  const TYPE_STATUS     = 'project:col:status';
  const TYPE_LIMIT      = 'project:col:limit';

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectColumnPHIDType::TYPECONST;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this column.',
            $author_handle);
        } else {
          if (!strlen($old)) {
            return pht(
              '%s named this column "%s".',
              $author_handle,
              $new);
          } else if (strlen($new)) {
            return pht(
              '%s renamed this column from "%s" to "%s".',
              $author_handle,
              $old,
              $new);
          } else {
            return pht(
              '%s removed the custom name of this column.',
              $author_handle);
          }
        }
      case self::TYPE_LIMIT:
        if (!$old) {
          return pht(
            '%s set the point limit for this column to %s.',
            $author_handle,
            $new);
        } else if (!$new) {
          return pht(
            '%s removed the point limit for this column.',
            $author_handle);
        } else {
          return pht(
            '%s changed point limit for this column from %s to %s.',
            $author_handle,
            $old,
            $new);
        }

      case self::TYPE_STATUS:
        switch ($new) {
          case PhabricatorProjectColumn::STATUS_ACTIVE:
            return pht(
              '%s marked this column visible.',
              $author_handle);
          case PhabricatorProjectColumn::STATUS_HIDDEN:
            return pht(
              '%s marked this column hidden.',
              $author_handle);
        }
        break;
    }

    return parent::getTitle();
  }

}
