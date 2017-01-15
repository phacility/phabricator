<?php

final class PhabricatorAuditTransaction
  extends PhabricatorModularTransaction {

  const TYPE_COMMIT = 'audit:commit';

  const MAILTAG_ACTION_CONCERN = 'audit-action-concern';
  const MAILTAG_ACTION_ACCEPT  = 'audit-action-accept';
  const MAILTAG_ACTION_RESIGN  = 'audit-action-resign';
  const MAILTAG_ACTION_CLOSE   = 'audit-action-close';
  const MAILTAG_ADD_AUDITORS   = 'audit-add-auditors';
  const MAILTAG_ADD_CCS        = 'audit-add-ccs';
  const MAILTAG_COMMENT        = 'audit-comment';
  const MAILTAG_COMMIT         = 'audit-commit';
  const MAILTAG_PROJECTS       = 'audit-projects';
  const MAILTAG_OTHER          = 'audit-other';

  public function getApplicationName() {
    return 'audit';
  }

  public function getBaseTransactionClass() {
    return 'DiffusionCommitTransactionType';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryCommitPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorAuditTransactionComment();
  }

  public function getApplicationTransactionViewObject() {
    return new PhabricatorAuditTransactionView();
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    switch ($this->getTransactionType()) {
    case self::TYPE_COMMIT:
      $data = $this->getNewValue();
      $blocks[] = $data['description'];
      break;
    }

    return $blocks;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $type = $this->getTransactionType();

    switch ($type) {
      case self::TYPE_COMMIT:
        $phids[] = $this->getObjectPHID();
        $data = $this->getNewValue();
        if ($data['authorPHID']) {
          $phids[] = $data['authorPHID'];
        }
        if ($data['committerPHID']) {
          $phids[] = $data['committerPHID'];
        }
        break;
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

  public function getActionName() {

    switch ($this->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
        switch ($this->getNewValue()) {
          case PhabricatorAuditActionConstants::CONCERN:
            return pht('Raised Concern');
          case PhabricatorAuditActionConstants::ACCEPT:
            return pht('Accepted');
          case PhabricatorAuditActionConstants::RESIGN:
            return pht('Resigned');
          case PhabricatorAuditActionConstants::CLOSE:
            return pht('Closed');
        }
        break;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return pht('Added Auditors');
      case self::TYPE_COMMIT:
        return pht('Committed');
    }

    return parent::getActionName();
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
          case PhabricatorAuditActionConstants::RESIGN:
            return 'black';
          case PhabricatorAuditActionConstants::CLOSE:
            return 'indigo';
        }
    }

    return parent::getColor();
  }

  public function getIcon() {

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ACTION:
        switch ($this->getNewValue()) {
          case PhabricatorAuditActionConstants::CONCERN:
            return 'fa-exclamation-circle';
          case PhabricatorAuditActionConstants::ACCEPT:
            return 'fa-check';
          case PhabricatorAuditActionConstants::RESIGN:
            return 'fa-plane';
          case PhabricatorAuditActionConstants::CLOSE:
            return 'fa-check';
        }
    }

    return parent::getIcon();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

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
      case self::TYPE_COMMIT:
        $author = null;
        if ($new['authorPHID']) {
          $author = $this->renderHandleLink($new['authorPHID']);
        } else {
          $author = $new['authorName'];
        }

        $committer = null;
        if ($new['committerPHID']) {
          $committer = $this->renderHandleLink($new['committerPHID']);
        } else if ($new['committerName']) {
          $committer = $new['committerName'];
        }

        $commit = $this->renderHandleLink($this->getObjectPHID());

        if (!$committer) {
          $committer = $author;
          $author = null;
        }

        if ($author) {
          $title = pht(
            '%s committed %s (authored by %s).',
            $committer,
            $commit,
            $author);
        } else {
          $title = pht(
            '%s committed %s.',
            $committer,
            $commit);
        }
        return $title;

      case PhabricatorAuditActionConstants::INLINE:
        return pht(
          '%s added inline comments.',
          $author_handle);

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

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_handle = $this->renderHandleLink($this->getAuthorPHID());
    $object_handle = $this->renderHandleLink($this->getObjectPHID());

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
      case self::TYPE_COMMIT:
        $author = null;
        if ($new['authorPHID']) {
          $author = $this->renderHandleLink($new['authorPHID']);
        } else {
          $author = $new['authorName'];
        }

        $committer = null;
        if ($new['committerPHID']) {
          $committer = $this->renderHandleLink($new['committerPHID']);
        } else if ($new['committerName']) {
          $committer = $new['committerName'];
        }

        if (!$committer) {
          $committer = $author;
          $author = null;
        }

        if ($author) {
          $title = pht(
            '%s committed %s (authored by %s).',
            $committer,
            $object_handle,
            $author);
        } else {
          $title = pht(
            '%s committed %s.',
            $committer,
            $object_handle);
        }
        return $title;

      case PhabricatorAuditActionConstants::INLINE:
        return pht(
          '%s added inline comments to %s.',
          $author_handle,
          $object_handle);

      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if ($add && $rem) {
          return pht(
            '%s edited auditors for %s; added: %s, removed: %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added auditors to %s: %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed auditors from %s: %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s added auditors to %s...',
            $author_handle,
            $object_handle);
        }

      case PhabricatorAuditActionConstants::ACTION:
        switch ($new) {
          case PhabricatorAuditActionConstants::ACCEPT:
            return pht(
              '%s accepted %s.',
              $author_handle,
              $object_handle);
          case PhabricatorAuditActionConstants::CONCERN:
            return pht(
              '%s raised a concern with %s.',
              $author_handle,
              $object_handle);
          case PhabricatorAuditActionConstants::RESIGN:
            return pht(
              '%s resigned from auditing %s.',
              $author_handle,
              $object_handle);
          case PhabricatorAuditActionConstants::CLOSE:
            return pht(
              '%s closed the audit of %s.',
              $author_handle,
              $object_handle);
        }

    }

    return parent::getTitleForFeed();
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    switch ($this->getTransactionType()) {
      case self::TYPE_COMMIT:
        $data = $this->getNewValue();
        return $story->renderSummary($data['summary']);
    }
    return parent::getBodyForFeed($story);
  }

  public function isInlineCommentTransaction() {
    switch ($this->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return true;
    }

    return parent::isInlineCommentTransaction();
  }

  public function getBodyForMail() {
    switch ($this->getTransactionType()) {
      case self::TYPE_COMMIT:
        $data = $this->getNewValue();
        return $data['description'];
    }

    return parent::getBodyForMail();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case DiffusionCommitAcceptTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_ACTION_ACCEPT;
        break;
      case DiffusionCommitConcernTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_ACTION_CONCERN;
        break;
      case DiffusionCommitResignTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_ACTION_RESIGN;
        break;
      case DiffusionCommitAuditorsTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_ADD_AUDITORS;
        break;
      case PhabricatorAuditActionConstants::ACTION:
        switch ($this->getNewValue()) {
          case PhabricatorAuditActionConstants::CONCERN:
            $tags[] = self::MAILTAG_ACTION_CONCERN;
            break;
          case PhabricatorAuditActionConstants::ACCEPT:
            $tags[] = self::MAILTAG_ACTION_ACCEPT;
            break;
          case PhabricatorAuditActionConstants::RESIGN:
            $tags[] = self::MAILTAG_ACTION_RESIGN;
            break;
          case PhabricatorAuditActionConstants::CLOSE:
            $tags[] = self::MAILTAG_ACTION_CLOSE;
            break;
        }
        break;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $tags[] = self::MAILTAG_ADD_AUDITORS;
        break;
      case PhabricatorAuditActionConstants::ADD_CCS:
        $tags[] = self::MAILTAG_ADD_CCS;
        break;
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case self::TYPE_COMMIT:
        $tags[] = self::MAILTAG_COMMIT;
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
            $tags[] = self::MAILTAG_PROJECTS;
            break;
          case PhabricatorObjectHasSubscriberEdgeType::EDGECONST:
            $tags[] = self::MAILTAG_ADD_CCS;
            break;
          default:
            $tags[] = self::MAILTAG_OTHER;
            break;
        }
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }
}
