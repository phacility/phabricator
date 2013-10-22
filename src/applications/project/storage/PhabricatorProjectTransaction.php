<?php

final class PhabricatorProjectTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME       = 'project:name';
  const TYPE_MEMBERS    = 'project:members';
  const TYPE_STATUS     = 'project:status';

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectPHIDTypeProject::TYPECONST;
  }

  public function getRequiredHandlePHIDs() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $req_phids = array();
    switch ($this->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        $req_phids = array_merge($add, $rem);
        break;
    }

    return array_merge($req_phids, parent::getRequiredHandlePHIDs());
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    switch ($this->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this project.',
            $author_handle);
        } else {
          return pht(
            '%s renamed this project from "%s" to "%s".',
            $author_handle,
            $old,
            $new);
        }
      case PhabricatorProjectTransaction::TYPE_STATUS:
        if ($old == 0) {
          return pht(
            '%s closed this project.',
            $author_handle);
        } else {
          return pht(
            '%s reopened this project.',
            $author_handle);
        }
      case PhabricatorProjectTransaction::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed project member(s), added %d: %s; removed %d: %s',
            $author_handle,
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          if (count($add) == 1 && (head($add) == $this->getAuthorPHID())) {
            return pht(
              '%s joined this project.',
              $author_handle);
          } else {
            return pht(
              '%s added %d project member(s): %s',
              $author_handle,
              count($add),
              $this->renderHandleList($add));
          }
        } else if ($rem) {
          if (count($rem) == 1 && (head($rem) == $this->getAuthorPHID())) {
            return pht(
              '%s left this project.',
              $author_handle);
          } else {
            return pht(
              '%s removed %d project member(s): %s',
              $author_handle,
              count($rem),
              $this->renderHandleList($rem));
          }
        }
    }

    return parent::getTitle();
  }


}
