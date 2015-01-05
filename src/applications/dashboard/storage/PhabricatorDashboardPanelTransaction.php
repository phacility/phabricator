<?php

final class PhabricatorDashboardPanelTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'dashpanel:name';
  const TYPE_ARCHIVE = 'dashboard:archive';

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getApplicationTransactionType() {
    return PhabricatorDashboardPanelPHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_link = $this->renderHandleLink($author_phid);

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
        if (!strlen($old)) {
          return pht(
            '%s created this panel.',
            $author_link);
        } else {
          return pht(
            '%s renamed this panel from "%s" to "%s".',
            $author_link,
            $old,
            $new);
        }
      case self::TYPE_ARCHIVE:
        if ($new) {
          return pht(
            '%s archived this panel.',
            $author_link);
        } else {
          return pht(
            '%s activated this panel.',
            $author_link);
        }
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_link = $this->renderHandleLink($author_phid);
    $object_link = $this->renderHandleLink($object_phid);

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
        if (!strlen($old)) {
          return pht(
            '%s created dashboard panel %s.',
            $author_link,
            $object_link);
        } else {
          return pht(
            '%s renamed dashboard panel %s from "%s" to "%s".',
            $author_link,
            $object_link,
            $old,
            $new);
        }
      case self::TYPE_ARCHIVE:
        if ($new) {
          return pht(
            '%s archived dashboard panel %s.',
            $author_link,
            $object_link);
        } else {
          return pht(
            '%s activated dashboard panel %s.',
            $author_link,
            $object_link);
        }
    }

    return parent::getTitleForFeed();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if (!strlen($old)) {
          return PhabricatorTransactions::COLOR_GREEN;
        }
        break;
    }

    return parent::getColor();
  }
}
