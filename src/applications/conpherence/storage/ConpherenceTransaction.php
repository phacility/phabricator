<?php

final class ConpherenceTransaction extends PhabricatorApplicationTransaction {

  const TYPE_TITLE           = 'title';
  const TYPE_TOPIC           = 'topic';
  const TYPE_PARTICIPANTS    = 'participants';
  const TYPE_DATE_MARKER     = 'date-marker';
  const TYPE_PICTURE         = 'picture';
  const TYPE_PICTURE_CROP    = 'picture-crop';

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
      case self::TYPE_TITLE:
      case self::TYPE_TOPIC:
      case self::TYPE_PICTURE:
      case self::TYPE_DATE_MARKER:
        return false;
      case self::TYPE_PICTURE_CROP:
        return true;
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_TOPIC:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case self::TYPE_PICTURE:
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

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_TITLE:
        if (strlen($old) && strlen($new)) {
          return pht(
            '%s renamed %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        } else {
          return pht(
            '%s created the room %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      break;
      case self::TYPE_TOPIC:
        if (strlen($new)) {
          return pht(
            '%s set the topic of %s to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $new);
        } else if (strlen($old)) {
          return pht(
            '%s deleted the topic in %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
      case self::TYPE_PICTURE:
        return pht(
          '%s updated the room image for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }
    return parent::getTitleForFeed();
  }

  private function getRoomTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
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
      case self::TYPE_TOPIC:
        if ($new) {
          $title = pht(
            '%s set the topic of this room to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else if ($old) {
          $title = pht(
            '%s deleted the room topic "%s"',
            $this->renderHandleLink($author_phid),
            $old);
        }
        return $title;
        break;
      case self::TYPE_PICTURE:
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

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $phids[] = $this->getAuthorPHID();
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_PICTURE:
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
