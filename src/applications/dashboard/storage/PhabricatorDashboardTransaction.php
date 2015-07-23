<?php

final class PhabricatorDashboardTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'dashboard:name';
  const TYPE_STATUS = 'dashboard:status';
  const TYPE_LAYOUT_MODE = 'dashboard:layoutmode';

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getApplicationTransactionType() {
    return PhabricatorDashboardDashboardPHIDType::TYPECONST;
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
            '%s created this dashboard.',
            $author_link);
        } else {
          return pht(
            '%s renamed this dashboard from "%s" to "%s".',
            $author_link,
            $old,
            $new);
        }
        break;
      case self::TYPE_STATUS:
        if ($new == PhabricatorDashboard::STATUS_ACTIVE) {
          return pht(
            '%s activated this dashboard',
            $author_link);
        } else {
          return pht(
            '%s archived this dashboard',
            $author_link);
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

    $author_link = $this->renderHandleLink($author_phid);
    $object_link = $this->renderHandleLink($object_phid);

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
        if (!strlen($old)) {
          return pht(
            '%s created dashboard %s.',
            $author_link,
            $object_link);
        } else {
          return pht(
            '%s renamed dashboard %s from "%s" to "%s".',
            $author_link,
            $object_link,
            $old,
            $new);
        }
        break;
      case self::TYPE_STATUS:
        if ($new == PhabricatorDashboard::STATUS_ACTIVE) {
          return pht(
            '%s activated dashboard %s.',
            $author_link,
            $object_link);
        } else {
          return pht(
            '%s archived dashboard %s.',
            $author_link,
            $object_link);
        }
        break;
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
      case self::TYPE_STATUS:
        if ($new == PhabricatorDashboard::STATUS_ACTIVE) {
          return PhabricatorTransactions::COLOR_GREEN;
        } else {
          return PhabricatorTransactions::COLOR_INDIGO;
        }
        break;
    }

    return parent::getColor();
  }

  public function getIcon() {
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return 'fa-pencil';
        break;
      case self::TYPE_STATUS:
        if ($new == PhabricatorDashboard::STATUS_ACTIVE) {
          return 'fa-check';
        } else {
          return 'fa-ban';
        }
        break;
      case self::TYPE_LAYOUT_MODE:
        return 'fa-columns';
        break;
    }
    return parent::getIcon();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_LAYOUT_MODE:
        return true;
    }
    return parent::shouldHide();
  }
}
