<?php

final class PhabricatorCalendarEventTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'calendar.name';
  const TYPE_START_DATE = 'calendar.startdate';
  const TYPE_END_DATE = 'calendar.enddate';
  const TYPE_STATUS = 'calendar.status';
  const TYPE_DESCRIPTION = 'calendar.description';

  const MAILTAG_CONTENT = 'calendar-content';
  const MAILTAG_OTHER = 'calendar-other';

  public function getApplicationName() {
    return 'calendar';
  }

  public function getApplicationTransactionType() {
    return PhabricatorCalendarEventPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorCalendarEventTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_START_DATE:
      case self::TYPE_END_DATE:
      case self::TYPE_STATUS:
      case self::TYPE_DESCRIPTION:
        $phids[] = $this->getObjectPHID();
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_START_DATE:
      case self::TYPE_END_DATE:
      case self::TYPE_STATUS:
      case self::TYPE_DESCRIPTION:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_START_DATE:
      case self::TYPE_END_DATE:
      case self::TYPE_STATUS:
      case self::TYPE_DESCRIPTION:
        return 'fa-pencil';
        break;
    }
    return parent::getIcon();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this event.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the name of this event from %s to %s.',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_START_DATE:
        if ($old) {
          return pht(
            '%s edited the start date of this event.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_END_DATE:
        if ($old) {
          return pht(
            '%s edited the end date of this event.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_STATUS:
        $old_name = PhabricatorCalendarEvent::getNameForStatus($old);
        $new_name = PhabricatorCalendarEvent::getNameForStatus($new);
        return pht(
          '%s updated the event status from %s to %s.',
          $this->renderHandleLink($author_phid),
          $old_name,
          $new_name);
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          "%s updated the event's description.",
          $this->renderHandleLink($author_phid));
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $viewer = $this->getViewer();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s changed the name of %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_START_DATE:
        if ($old) {
          $old = phabricator_datetime($old, $viewer);
          $new = phabricator_datetime($new, $viewer);
          return pht(
            '%s changed the start date of %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_END_DATE:
        if ($old) {
          $old = phabricator_datetime($old, $viewer);
          $new = phabricator_datetime($new, $viewer);
          return pht(
            '%s edited the end date of %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_STATUS:
        $old_name = PhabricatorCalendarEvent::getNameForStatus($old);
        $new_name = PhabricatorCalendarEvent::getNameForStatus($new);
        return pht(
          '%s updated the status of %s from %s to %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old_name,
          $new_name);
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_START_DATE:
      case self::TYPE_END_DATE:
      case self::TYPE_STATUS:
      case self::TYPE_DESCRIPTION:
        return PhabricatorTransactions::COLOR_GREEN;
    }

    return parent::getColor();
  }


  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($this->getOldValue() !== null);
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        return $this->renderTextCorpusChangeDetails(
          $viewer,
          $old,
          $new);
    }

    return parent::renderChangeDetails($viewer);
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case self::TYPE_START_DATE:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case self::TYPE_END_DATE:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case self::TYPE_STATUS:
        $tags[] = self::MAILTAG_OTHER;
        break;
      case self::TYPE_DESCRIPTION:
        $tags[] = self::MAILTAG_CONTENT;
        break;
    }
    return $tags;
  }

}
