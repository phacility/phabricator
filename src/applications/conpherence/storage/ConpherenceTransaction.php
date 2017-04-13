<?php

final class ConpherenceTransaction
  extends PhabricatorModularTransaction {

  const TYPE_PARTICIPANTS    = 'participants';
  const TYPE_DATE_MARKER     = 'date-marker';

  public function getApplicationName() {
    return 'conpherence';
  }

  public function getApplicationTransactionType() {
    return PhabricatorConpherenceThreadPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ConpherenceTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'ConpherenceThreadTransactionType';
  }

  public function getNoEffectDescription() {
    switch ($this->getTransactionType()) {
      case self::TYPE_PARTICIPANTS:
        return pht(
          'You can not add a participant who has already been added.');
        break;
    }

    return parent::getNoEffectDescription();
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_PARTICIPANTS:
        return ($old === null);
      case self::TYPE_DATE_MARKER:
        return false;
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        return $this->getRoomTitle();
        break;
      case self::TYPE_PARTICIPANTS:
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

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids[] = $this->getAuthorPHID();
    switch ($this->getTransactionType()) {
      case self::TYPE_DATE_MARKER:
        break;
      case self::TYPE_PARTICIPANTS:
        $phids = array_merge($phids, $this->getOldValue());
        $phids = array_merge($phids, $this->getNewValue());
        break;
    }

    return $phids;
  }
}
