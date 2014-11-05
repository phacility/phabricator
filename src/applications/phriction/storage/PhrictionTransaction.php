<?php

final class PhrictionTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'title';
  const TYPE_CONTENT = 'content';
  const TYPE_DELETE  = 'delete';

  const MAILTAG_TITLE = 'phriction-title';
  const MAILTAG_CONTENT = 'phriction-content';
  const MAILTAG_DELETE  = 'phriction-delete';

  public function getApplicationName() {
    return 'phriction';
  }

  public function getApplicationTransactionType() {
    return PhrictionDocumentPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhrictionTransactionComment();
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        $blocks[] = $this->getNewValue();
        break;
    }

    return $blocks;
  }

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        if ($this->getOldValue() === null) {
          return true;
        } else {
          return false;
        }
        break;
    }

    return parent::shouldHide();
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        return 1.4;
      case self::TYPE_CONTENT:
        return 1.3;
      case self::TYPE_DELETE:
        return 1.5;
    }

    return parent::getActionStrength();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht('Created');
        }

        return pht('Retitled');

      case self::TYPE_CONTENT:
        return pht('Edited');

      case self::TYPE_DELETE:
        return pht('Deleted');

    }

    return parent::getActionName();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
        return 'fa-pencil';
      case self::TYPE_DELETE:
        return 'fa-times';
    }

    return parent::getIcon();
  }


  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s created this document.',
            $this->renderHandleLink($author_phid));
        }
        return pht(
          '%s changed the title from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);

      case self::TYPE_CONTENT:
        return pht(
          '%s edited the document content.',
          $this->renderHandleLink($author_phid));

      case self::TYPE_DELETE:
        return pht(
          '%s deleted this document.',
          $this->renderHandleLink($author_phid));


    }

    return parent::getTitle();
  }

  public function getTitleForFeed(PhabricatorFeedStory $story) {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }

        return pht(
          '%s renamed %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old,
          $new);

      case self::TYPE_CONTENT:
        return pht(
          '%s edited the content of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));

      case self::TYPE_DELETE:
        return pht(
          '%s deleted %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));

    }
    return parent::getTitleForFeed($story);
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        $tags[] = self::MAILTAG_TITLE;
        break;
      case self::TYPE_CONTENT:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case self::TYPE_DELETE:
        $tags[] = self::MAILTAG_DELETE;
        break;

    }
    return $tags;
  }

}
