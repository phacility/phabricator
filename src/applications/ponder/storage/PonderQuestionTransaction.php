<?php

final class PonderQuestionTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'ponder.question:question';
  const TYPE_CONTENT = 'ponder.question:content';
  const TYPE_ANSWERS = 'ponder.question:answer';
  const TYPE_STATUS = 'ponder.question:status';
  const TYPE_ANSWERWIKI = 'ponder.question:wiki';

  const MAILTAG_DETAILS = 'question:details';
  const MAILTAG_COMMENT = 'question:comment';
  const MAILTAG_ANSWERS = 'question:answer';
  const MAILTAG_OTHER = 'question:other';

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_questiontransaction';
  }

  public function getApplicationTransactionType() {
    return PonderQuestionPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderQuestionTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_ANSWERS:
        $phids[] = $this->getNewAnswerPHID();
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

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s asked this question.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s edited the question title from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_CONTENT:
        return pht(
          '%s edited the question description.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ANSWERWIKI:
        return pht(
          '%s edited the question answer wiki.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ANSWERS:
        $answer_handle = $this->getHandle($this->getNewAnswerPHID());
        $question_handle = $this->getHandle($object_phid);

        return pht(
          '%s answered %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return pht(
              '%s reopened this question.',
              $this->renderHandleLink($author_phid));
          case PonderQuestionStatus::STATUS_CLOSED_RESOLVED:
            return pht(
              '%s closed this question as resolved.',
              $this->renderHandleLink($author_phid));
          case PonderQuestionStatus::STATUS_CLOSED_OBSOLETE:
            return pht(
              '%s closed this question as obsolete.',
              $this->renderHandleLink($author_phid));
          case PonderQuestionStatus::STATUS_CLOSED_INVALID:
            return pht(
              '%s closed this question as invalid.',
              $this->renderHandleLink($author_phid));
        }
    }

    return parent::getTitle();
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
      case self::TYPE_STATUS:
      case self::TYPE_ANSWERWIKI:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      case self::TYPE_ANSWERS:
        $tags[] = self::MAILTAG_ANSWERS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
      case self::TYPE_ANSWERWIKI:
        return 'fa-pencil';
      case self::TYPE_STATUS:
        return PonderQuestionStatus::getQuestionStatusIcon($new);
      case self::TYPE_ANSWERS:
        return 'fa-plus';
    }

    return parent::getIcon();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
      case self::TYPE_ANSWERWIKI:
        return PhabricatorTransactions::COLOR_BLUE;
      case self::TYPE_ANSWERS:
        return PhabricatorTransactions::COLOR_GREEN;
      case self::TYPE_STATUS:
        return PonderQuestionStatus::getQuestionStatusTagColor($new);
    }
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
      case self::TYPE_ANSWERWIKI:
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

  public function getActionStrength() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return 3;
        }
        break;
      case self::TYPE_ANSWERS:
        return 2;
    }

    return parent::getActionStrength();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht('Asked');
        }
        break;
      case self::TYPE_ANSWERS:
        return pht('Answered');
    }

    return parent::getActionName();
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
            '%s asked a question: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s edited the title of %s (was "%s")',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old);
        }
      case self::TYPE_CONTENT:
        return pht(
          '%s edited the description of %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_ANSWERWIKI:
        return pht(
          '%s edited the answer wiki for %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_ANSWERS:
        $answer_handle = $this->getHandle($this->getNewAnswerPHID());
        $question_handle = $this->getHandle($object_phid);
        return pht(
          '%s answered %s',
          $this->renderHandleLink($author_phid),
          $answer_handle->renderLink($question_handle->getFullName()));
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return pht(
              '%s reopened %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PonderQuestionStatus::STATUS_CLOSED_RESOLVED:
            return pht(
              '%s closed %s as resolved.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PonderQuestionStatus::STATUS_CLOSED_INVALID:
            return pht(
              '%s closed %s as invalid.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PonderQuestionStatus::STATUS_CLOSED_OBSOLETE:
            return pht(
              '%s closed %s as obsolete.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
        }
    }

    return parent::getTitleForFeed();
  }

  public function getRemarkupBodyForFeed(PhabricatorFeedStory $story) {
    $text = null;
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        $text = $this->getNewValue();
        break;
    }
    return $text;
  }

  /**
   * Currently the application only supports adding answers one at a time.
   * This data is stored as a list of phids. Use this function to get the
   * new phid.
   */
  private function getNewAnswerPHID() {
    $new = $this->getNewValue();
    $old = $this->getOldValue();
    $add = array_diff($new, $old);

    if (count($add) != 1) {
      throw new Exception(
        pht('There should be only one answer added at a time.'));
    }

    return reset($add);
  }

}
