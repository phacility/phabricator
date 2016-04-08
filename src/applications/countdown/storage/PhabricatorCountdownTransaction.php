<?php

final class PhabricatorCountdownTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'countdown:title';
  const TYPE_EPOCH = 'countdown:epoch';
  const TYPE_DESCRIPTION = 'countdown:description';

  const MAILTAG_DETAILS = 'countdown:details';
  const MAILTAG_COMMENT = 'countdown:comment';
  const MAILTAG_OTHER  = 'countdown:other';

  public function getApplicationName() {
    return 'countdown';
  }

  public function getApplicationTransactionType() {
    return PhabricatorCountdownCountdownPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorCountdownTransactionComment();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_TITLE:
        return pht(
          '%s renamed this countdown from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the description of this countdown.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_EPOCH:
        return pht(
          '%s updated this countdown to end on %s.',
          $this->renderHandleLink($author_phid),
          phabricator_datetime($new, $this->getViewer()));
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
        return pht(
          '%s renamed %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the description of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_EPOCH:
        return pht(
          '%s edited the end date of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return parent::getTitleForFeed();
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case self::TYPE_TITLE:
      case self::TYPE_EPOCH:
      case self::TYPE_DESCRIPTION:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
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
