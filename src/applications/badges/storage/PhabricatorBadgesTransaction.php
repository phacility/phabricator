<?php

final class PhabricatorBadgesTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'badges:name';
  const TYPE_DESCRIPTION = 'badges:description';
  const TYPE_QUALITY = 'badges:quality';
  const TYPE_ICON = 'badges:icon';
  const TYPE_STATUS = 'badges:status';
  const TYPE_FLAVOR = 'badges:flavor';

  const MAILTAG_DETAILS = 'badges:details';
  const MAILTAG_COMMENT = 'badges:comment';
  const MAILTAG_OTHER  = 'badges:other';

  public function getApplicationName() {
    return 'badges';
  }

  public function getApplicationTransactionType() {
    return PhabricatorBadgesPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorBadgesTransactionComment();
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
            '%s created this badge.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this badge from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_FLAVOR:
        if ($old === null) {
          return pht(
            '%s set the flavor text for this badge.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s updated the flavor text for this badge.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_DESCRIPTION:
        if ($old === null) {
          return pht(
            '%s set the description for this badge.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s updated the description for this badge.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_ICON:
        if ($old === null) {
          return pht(
            '%s set the icon for this badge as "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          $icon_map = PhabricatorBadgesBadge::getIconNameMap();
          $icon_new = idx($icon_map, $new, $new);
          $icon_old = idx($icon_map, $old, $old);
          return pht(
            '%s updated the icon for this badge from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $icon_old,
            $icon_new);
        }
        break;
      case self::TYPE_QUALITY:
        if ($old === null) {
          return pht(
            '%s set the quality for this badge as "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          $qual_map = PhabricatorBadgesBadge::getQualityNameMap();
          $qual_new = idx($qual_map, $new, $new);
          $qual_old = idx($qual_map, $old, $old);
          return pht(
            '%s updated the quality for this badge from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $qual_old,
            $qual_new);
        }
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
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

        } else {
          return pht(
            '%s renamed %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
      case self::TYPE_FLAVOR:
        return pht(
          '%s updated the flavor text for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_ICON:
        return pht(
          '%s updated the icon for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_QUALITY:
        return pht(
          '%s updated the quality level for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_STATUS:
        switch ($new) {
          case PhabricatorBadgesBadge::STATUS_OPEN:
            return pht(
              '%s activated %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PhabricatorBadgesBadge::STATUS_CLOSED:
            return pht(
              '%s archived %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
        }
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case self::TYPE_NAME:
      case self::TYPE_DESCRIPTION:
      case self::TYPE_FLAVOR:
      case self::TYPE_ICON:
      case self::TYPE_STATUS:
      case self::TYPE_QUALITY:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }


  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($this->getOldValue() !== null);
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
  }
}
