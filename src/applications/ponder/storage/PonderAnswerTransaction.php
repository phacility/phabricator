<?php

final class PonderAnswerTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CONTENT = 'ponder.answer:content';

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_answertransaction';
  }

  public function getApplicationTransactionType() {
    return PonderAnswerPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderAnswerTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        $phids[] = $this->getObjectPHID();
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

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return pht(
          '%s edited %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return pht(
          '%s updated %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
    }

    return parent::getTitleForFeed();
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    $new = $this->getNewValue();

    $body = null;

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return phutil_escape_html_newlines(
          id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(128)
          ->truncateString($new));
        break;
    }
    return parent::getBodyForFeed($story);
  }


  public function hasChangeDetails() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return $old !== null;
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
