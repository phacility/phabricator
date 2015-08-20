<?php

final class PonderAnswerTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CONTENT = 'ponder.answer:content';
  const TYPE_STATUS = 'ponder.answer:status';
  const TYPE_QUESTION_ID = 'ponder.answer:question-id';

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
      case self::TYPE_STATUS:
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

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case self::TYPE_QUESTION_ID:
        return true;
    }
    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        if ($old === '') {
          return pht(
            '%s added %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s edited %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
      case self::TYPE_STATUS:
        if ($new == PonderAnswerStatus::ANSWER_STATUS_VISIBLE) {
          return pht(
            '%s marked %s as visible.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else if ($new == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
          return pht(
            '%s marked %s as hidden.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
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

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        if ($old === '') {
          return pht(
            '%s added %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s updated %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
      case self::TYPE_STATUS:
        if ($new == PonderAnswerStatus::ANSWER_STATUS_VISIBLE) {
          return pht(
            '%s marked %s as visible.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else if ($new == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
          return pht(
            '%s marked %s as hidden.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
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
