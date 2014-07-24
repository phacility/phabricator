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

  public function getTitleForFeed(PhabricatorFeedStory $story) {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        $answer = $story->getObject($object_phid);
        $question = $answer->getQuestion();
        $answer_handle = $this->getHandle($object_phid);
        $link = $answer_handle->renderLink(
          $question->getFullTitle());

        return pht(
          '%s updated their answer to %s',
          $this->renderHandleLink($author_phid),
          $link);
    }

    return parent::getTitleForFeed($story);
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    $new = $this->getNewValue();

    $body = null;

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return phutil_escape_html_newlines(
          phutil_utf8_shorten($new, 128));
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
