<?php

final class ConpherenceTransaction
  extends PhabricatorModularTransaction {

  const TYPE_PARTICIPANTS    = 'participants';

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
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
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

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids[] = $this->getAuthorPHID();
    switch ($this->getTransactionType()) {
      case self::TYPE_PARTICIPANTS:
        $phids = array_merge($phids, $this->getOldValue());
        $phids = array_merge($phids, $this->getNewValue());
        break;
    }

    return $phids;
  }
}
