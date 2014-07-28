<?php

final class PhabricatorAuditTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'audit';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryCommitPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorAuditTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }

        foreach (array_keys($old + $new) as $phid) {
          $phids[] = $phid;
        }
        break;
    }

    return $phids;
  }

  public function getColor() {

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ACTION:
        switch ($this->getNewValue()) {
          case PhabricatorAuditActionConstants::CONCERN:
            return 'red';
          case PhabricatorAuditActionConstants::ACCEPT:
            return 'green';
        }
    }

    return parent::getColor();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_handle = $this->getHandle($this->getAuthorPHID())->renderLink();

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }
        $add = array_keys(array_diff_key($new, $old));
        $rem = array_keys(array_diff_key($old, $new));
        break;
    }

    switch ($type) {
      case PhabricatorAuditActionConstants::INLINE:
        break;
      case PhabricatorAuditActionConstants::ADD_CCS:
        if ($add && $rem) {
          return pht(
            '%s edited subscribers; added: %s, removed: %s.',
            $author_handle,
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added subscribers: %s.',
            $author_handle,
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed subscribers: %s.',
            $author_handle,
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s added subscribers...',
            $author_handle);
        }

      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if ($add && $rem) {
          return pht(
            '%s edited auditors; added: %s, removed: %s.',
            $author_handle,
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added auditors: %s.',
            $author_handle,
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed auditors: %s.',
            $author_handle,
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s added auditors...',
            $author_handle);
        }

      case PhabricatorAuditActionConstants::ACTION:
        switch ($new) {
          case PhabricatorAuditActionConstants::ACCEPT:
            return pht(
              '%s accepted this commit.',
              $author_handle);
          case PhabricatorAuditActionConstants::CONCERN:
            return pht(
              '%s raised a concern with this commit.',
              $author_handle);
          case PhabricatorAuditActionConstants::RESIGN:
            return pht(
              '%s resigned from this audit.',
              $author_handle);
          case PhabricatorAuditActionConstants::CLOSE:
            return pht(
              '%s closed this audit.',
              $author_handle);
        }

    }

    return parent::getTitle();
  }

}
