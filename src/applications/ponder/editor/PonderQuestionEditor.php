<?php

final class PonderQuestionEditor
  extends PonderEditor {

  private $answer;

  public function getEditorObjectsDescription() {
    return pht('Ponder Questions');
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
        case PonderQuestionTransaction::TYPE_ANSWERS:
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
        case PonderQuestionTransaction::TYPE_ANSWERS:
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
    $types[] = PonderQuestionTransaction::TYPE_TITLE;
    $types[] = PonderQuestionTransaction::TYPE_CONTENT;
    $types[] = PonderQuestionTransaction::TYPE_ANSWERS;
    $types[] = PonderQuestionTransaction::TYPE_STATUS;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
        return $object->getTitle();
      case PonderQuestionTransaction::TYPE_CONTENT:
        return $object->getContent();
      case PonderQuestionTransaction::TYPE_ANSWERS:
        return mpull($object->getAnswers(), 'getPHID');
      case PonderQuestionTransaction::TYPE_STATUS:
        return $object->getStatus();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
      case PonderQuestionTransaction::TYPE_CONTENT:
      case PonderQuestionTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
      case PonderQuestionTransaction::TYPE_ANSWERS:
        $raw_new_value = $xaction->getNewValue();
        $new_value = array();
        foreach ($raw_new_value as $key => $answers) {
          $phids = array();
          foreach ($answers as $answer) {
            $obj = idx($answer, 'answer');
            if (!$answer) {
              continue;
            }
            $phids[] = $obj->getPHID();
          }
          $new_value[$key] = $phids;
        }
        $xaction->setNewValue($new_value);
        return $this->getPHIDTransactionNewValue($xaction);
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        break;
      case PonderQuestionTransaction::TYPE_CONTENT:
        $object->setContent($xaction->getNewValue());
        break;
      case PonderQuestionTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        break;
      case PonderQuestionTransaction::TYPE_ANSWERS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        $add = array_diff_key($new, $old);
        $rem = array_diff_key($old, $new);

        $count = $object->getAnswerCount();
        $count += count($add);
        $count -= count($rem);

        $object->setAnswerCount($count);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PonderQuestionTransaction::TYPE_TITLE:
      case PonderQuestionTransaction::TYPE_CONTENT:
      case PonderQuestionTransaction::TYPE_STATUS:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function supportsSearch() {
    return true;
  }

  protected function getFeedStoryType() {
    return 'PonderTransactionFeedStory';
  }

  protected function getFeedStoryData(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $data = parent::getFeedStoryData($object, $xactions);
    $answer = $this->getAnswer();
    if ($answer) {
      $data['answerPHID'] = $answer->getPHID();
    }

    return $data;
 }

  protected function shouldImplyCC(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_ANSWERS:
        return true;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PonderQuestionReplyHandler())
      ->setMailReceiver($object);
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
      if ($type == PonderQuestionTransaction::TYPE_CONTENT) {
        if ($old === null) {
          $body->addRawSection($new);
        }
      }
      // If the user gave an answer, add the answer text. Also update
      // the header and uri to be more answer-specific.
      if ($type == PonderQuestionTransaction::TYPE_ANSWERS) {
        $answer = $this->getAnswer();
        $body->addRawSection($answer->getContent());
        $header = pht('ANSWER DETAIL');
        $uri = $answer->getURI();
      }
    }

    $body->addLinkSection(
      $header,
      PhabricatorEnv::getProductionURI($uri));

    return $body;
  }

}
