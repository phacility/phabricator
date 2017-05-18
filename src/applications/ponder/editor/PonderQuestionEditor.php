<?php

final class PonderQuestionEditor
  extends PonderEditor {

  private $answer;

  public function getEditorObjectsDescription() {
    return pht('Ponder Questions');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this question.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  /**
   * This is used internally on @{method:applyInitialEffects} if a transaction
   * of type PonderQuestionTransaction::TYPE_ANSWERS is in the mix. The value
   * is set to the //last// answer in the transactions. Practically, one
   * answer is given at a time in the application, though theoretically
   * this is buggy.
   *
   * The answer is used in emails to generate proper links.
   */
  private function setAnswer(PonderAnswer $answer) {
    $this->answer = $answer;
    return $this;
  }
  private function getAnswer() {
    return $this->answer;
  }

  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PonderQuestionAnswerTransaction::TRANSACTIONTYPE:
          return true;
      }
    }

    return false;
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PonderQuestionAnswerTransaction::TRANSACTIONTYPE:
          $new_value = $xaction->getNewValue();
          $new = idx($new_value, '+', array());
          foreach ($new as $new_answer) {
            $answer = idx($new_answer, 'answer');
            if (!$answer) {
              continue;
            }
            $answer->save();
            $this->setAnswer($answer);
          }
          break;
      }
    }
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;

    return $types;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldImplyCC(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionAnswerTransaction::TRANSACTIONTYPE:
        return false;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
      foreach ($xactions as $xaction) {
        switch ($xaction->getTransactionType()) {
          case PonderQuestionAnswerTransaction::TRANSACTIONTYPE:
            return false;
        }
      }
      return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
      foreach ($xactions as $xaction) {
        switch ($xaction->getTransactionType()) {
          case PonderQuestionAnswerTransaction::TRANSACTIONTYPE:
            return false;
        }
      }
      return true;
  }

  public function getMailTagsMap() {
    return array(
      PonderQuestionTransaction::MAILTAG_DETAILS =>
        pht('Someone changes the questions details.'),
      PonderQuestionTransaction::MAILTAG_ANSWERS =>
        pht('Someone adds a new answer.'),
      PonderQuestionTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on the question.'),
      PonderQuestionTransaction::MAILTAG_OTHER =>
        pht('Other question activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PonderQuestionReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();
    $original_title = $object->getOriginalTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("Q{$id}: {$title}")
      ->addHeader('Thread-Topic', "Q{$id}: {$original_title}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $header = pht('QUESTION DETAIL');
    $uri = '/Q'.$object->getID();
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      $old = $xaction->getOldValue();
      $new = $xaction->getNewValue();
      // If the user just asked the question, add the question text.
      if ($type == PonderQuestionContentTransaction::TRANSACTIONTYPE) {
        if ($old === null) {
          $body->addRawSection($new);
        }
      }
    }

    $body->addLinkSection(
      $header,
      PhabricatorEnv::getProductionURI($uri));

    return $body;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new HeraldPonderQuestionAdapter())
      ->setQuestion($object);
  }

}
