<?php

final class PhabricatorPhurlURLTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'phurl.name';
  const TYPE_URL = 'phurl.longurl';
  const TYPE_ALIAS = 'phurl.alias';
  const TYPE_DESCRIPTION = 'phurl.description';

  const MAILTAG_DETAILS = 'phurl-details';

  public function getApplicationName() {
    return 'phurl';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPhurlURLPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorPhurlURLTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_URL:
      case self::TYPE_ALIAS:
      case self::TYPE_DESCRIPTION:
        $phids[] = $this->getObjectPHID();
        break;
    }

    return $phids;
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
    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_URL:
      case self::TYPE_ALIAS:
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
            '%s created this URL.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the name of the URL from %s to %s.',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_URL:
        if ($old === null) {
          return pht(
            '%s set the destination of the URL to %s.',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the destination of the URL from %s to %s.',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_ALIAS:
        if ($old === null) {
          return pht(
            '%s set the alias of the URL to %s.',
            $this->renderHandleLink($author_phid),
            $new);
        } else if ($new === null) {
          return pht(
            '%s removed the alias of the URL.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the alias of the URL from %s to %s.',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_DESCRIPTION:
        return pht(
          "%s updated the URL's description.",
          $this->renderHandleLink($author_phid));
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
            '%s created %s.',
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
      case self::TYPE_URL:
        if ($old === null) {
          return pht(
            '%s set the destination of %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $new);
        } else {
          return pht(
            '%s changed the destination of %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        }
      case self::TYPE_ALIAS:
        if ($old === null) {
          return pht(
            '%s set the alias of %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $new);
        } else if ($new === null) {
          return pht(
            '%s removed the alias of %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s changed the alias of %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        }
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return parent::getTitleForFeed();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_URL:
      case self::TYPE_ALIAS:
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
      case self::TYPE_DESCRIPTION:
      case self::TYPE_URL:
      case self::TYPE_ALIAS:
        $tags[] = self::MAILTAG_DETAILS;
        break;
    }
    return $tags;
  }

}
