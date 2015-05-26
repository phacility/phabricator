<?php

final class PhameBlogTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME        = 'phame.blog.name';
  const TYPE_DESCRIPTION = 'phame.blog.description';
  const TYPE_DOMAIN      = 'phame.blog.domain';
  const TYPE_SKIN        = 'phame.blog.skin';

  public function getApplicationName() {
    return 'phame';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPhameBlogPHIDType::TYPECONST;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return 'fa-plus';
        } else {
          return 'fa-pencil';
        }
        break;
      case self::TYPE_DESCRIPTION:
      case self::TYPE_DOMAIN:
      case self::TYPE_SKIN:
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
      case self:TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this blog.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s updated the blog\'s name to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the blog\'s description.',
          $this->renderHandleLink($author_phid));
        break;
      case self::TYPE_DOMAIN:
        return pht(
          '%s updated the blog\'s domain to "%s".',
          $this->renderHandleLink($author_phid),
          $new);
        break;
      case self::TYPE_SKIN:
        return pht(
          '%s updated the blog\'s skin to "%s".',
          $this->renderHandleLink($author_phid),
          $new);
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
            '%s updated the name for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_DOMAIN:
        return pht(
          '%s updated the domain for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_SKIN:
        return pht(
          '%s updated the skin for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getColor() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return PhabricatorTransactions::COLOR_GREEN;
        }
        break;
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

}
