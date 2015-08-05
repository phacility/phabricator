<?php

final class PhrictionTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'title';
  const TYPE_CONTENT = 'content';
  const TYPE_DELETE  = 'delete';
  const TYPE_MOVE_TO = 'move-to';
  const TYPE_MOVE_AWAY = 'move-away';

  const MAILTAG_TITLE       = 'phriction-title';
  const MAILTAG_CONTENT     = 'phriction-content';
  const MAILTAG_DELETE      = 'phriction-delete';
  const MAILTAG_SUBSCRIBERS = 'phriction-subscribers';
  const MAILTAG_OTHER       = 'phriction-other';

  public function getApplicationName() {
    return 'phriction';
  }

  public function getApplicationTransactionType() {
    return PhrictionDocumentPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhrictionTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();
    $new = $this->getNewValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_MOVE_TO:
      case self::TYPE_MOVE_AWAY:
        $phids[] = $new['phid'];
        break;
      case self::TYPE_TITLE:
        if ($this->getMetadataValue('stub:create:phid')) {
          $phids[] = $this->getMetadataValue('stub:create:phid');
        }
        break;
    }


    return $phids;
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

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case self::TYPE_MOVE_TO:
      case self::TYPE_MOVE_AWAY:
        return true;
      case self::TYPE_TITLE:
        return $this->getMetadataValue('stub:create:phid', false);
    }
    return parent::shouldHideForMail($xactions);
  }

  public function shouldHideForFeed() {
    switch ($this->getTransactionType()) {
      case self::TYPE_MOVE_TO:
      case self::TYPE_MOVE_AWAY:
        return true;
      case self::TYPE_TITLE:
        return $this->getMetadataValue('stub:create:phid', false);
    }
    return parent::shouldHideForFeed();
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        return 1.4;
      case self::TYPE_CONTENT:
        return 1.3;
      case self::TYPE_DELETE:
        return 1.5;
      case self::TYPE_MOVE_TO:
      case self::TYPE_MOVE_AWAY:
        return 1.0;
    }

    return parent::getActionStrength();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          if ($this->getMetadataValue('stub:create:phid')) {
            return pht('Stubbed');
          } else {
            return pht('Created');
          }
        }

        return pht('Retitled');

      case self::TYPE_CONTENT:
        return pht('Edited');

      case self::TYPE_DELETE:
        return pht('Deleted');

      case self::TYPE_MOVE_TO:
        return pht('Moved');

      case self::TYPE_MOVE_AWAY:
        return pht('Moved Away');

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
      case self::TYPE_MOVE_TO:
      case self::TYPE_MOVE_AWAY:
        return 'fa-arrows';
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
          if ($this->getMetadataValue('stub:create:phid')) {
            return pht(
              '%s stubbed out this document when creating %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink(
                $this->getMetadataValue('stub:create:phid')));
          } else {
            return pht(
              '%s created this document.',
              $this->renderHandleLink($author_phid));
          }
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

      case self::TYPE_MOVE_TO:
        return pht(
          '%s moved this document from %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($new['phid']));

      case self::TYPE_MOVE_AWAY:
        return pht(
          '%s moved this document to %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($new['phid']));

    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
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
    return parent::getTitleForFeed();
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_SUBSCRIBERS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
