<?php

final class ConpherenceTransaction extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'conpherence';
  }

  public function getApplicationTransactionType() {
    return PhabricatorConpherenceThreadPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ConpherenceTransactionComment();
  }

  public function getNoEffectDescription() {
    switch ($this->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        return pht(
          'You can not add a participant who has already been added.');
        break;
    }

    return parent::getNoEffectDescription();
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        return ($old === null);
      case ConpherenceTransactionType::TYPE_TITLE:
      case ConpherenceTransactionType::TYPE_PICTURE:
      case ConpherenceTransactionType::TYPE_DATE_MARKER:
        return false;
      case ConpherenceTransactionType::TYPE_FILES:
        return true;
      case ConpherenceTransactionType::TYPE_PICTURE_CROP:
        return true;
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case ConpherenceTransactionType::TYPE_PICTURE:
        if ($this->getObject()->getIsRoom()) {
          return $this->getRoomTitle();
        } else {
          return $this->getThreadTitle();
        }
        break;
      case ConpherenceTransactionType::TYPE_FILES:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          $title = pht(
            '%s edited files(s), added %d and removed %d.',
            $this->renderHandleLink($author_phid),
            count($add),
            count($rem));
        } else if ($add) {
          $title = pht(
            '%s added %d files(s).',
            $this->renderHandleLink($author_phid),
            count($add));
        } else {
          $title = pht(
            '%s removed %d file(s).',
            $this->renderHandleLink($author_phid),
            count($rem));
        }
        return $title;
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          $title = pht(
            '%s edited participant(s), added %d: %s; removed %d: %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          $title = pht(
            '%s added %d participant(s): %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add));
        } else {
          $title = pht(
            '%s removed %d participant(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
        }
        return $title;
        break;
    }

    return parent::getTitle();
  }

  private function getRoomTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
        if ($old && $new) {
          $title = pht(
            '%s renamed this room from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        } else if ($old) {
          $title = pht(
            '%s deleted the room name "%s".',
            $this->renderHandleLink($author_phid),
            $old);
        } else {
          $title = pht(
            '%s named this room "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        return $title;
        break;
      case ConpherenceTransactionType::TYPE_PICTURE:
        return pht(
          '%s updated the room image.',
          $this->renderHandleLink($author_phid));
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return pht(
          '%s changed the visibility of this room from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return pht(
          '%s changed the edit policy of this room from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return pht(
          '%s changed the join policy of this room from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
        break;
    }
  }

  private function getThreadTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
        if ($old && $new) {
          $title = pht(
            '%s renamed this thread from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        } else if ($old) {
          $title = pht(
            '%s deleted the thread name "%s".',
            $this->renderHandleLink($author_phid),
            $old);
        } else {
          $title = pht(
            '%s named this thread "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        return $title;
        break;
      case ConpherenceTransactionType::TYPE_PICTURE:
        return pht(
          '%s updated the room image.',
          $this->renderHandleLink($author_phid));
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return pht(
          '%s changed the visibility of this thread from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return pht(
          '%s changed the edit policy of this thread from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
        break;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return pht(
          '%s changed the join policy of this thread from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
        break;
    }
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids[] = $this->getAuthorPHID();
    switch ($this->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
      case ConpherenceTransactionType::TYPE_PICTURE:
      case ConpherenceTransactionType::TYPE_FILES:
      case ConpherenceTransactionType::TYPE_DATE_MARKER:
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        $phids = array_merge($phids, $this->getOldValue());
        $phids = array_merge($phids, $this->getNewValue());
        break;
    }

    return $phids;
  }
}
