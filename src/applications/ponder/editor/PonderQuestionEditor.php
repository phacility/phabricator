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
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_SPACE;

    $types[] = PonderQuestionTransaction::TYPE_TITLE;
    $types[] = PonderQuestionTransaction::TYPE_CONTENT;
    $types[] = PonderQuestionTransaction::TYPE_ANSWERS;
    $types[] = PonderQuestionTransaction::TYPE_STATUS;
    $types[] = PonderQuestionTransaction::TYPE_ANSWERWIKI;

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
      case PonderQuestionTransaction::TYPE_ANSWERWIKI:
        return $object->getAnswerWiki();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
      case PonderQuestionTransaction::TYPE_CONTENT:
      case PonderQuestionTransaction::TYPE_STATUS:
      case PonderQuestionTransaction::TYPE_ANSWERWIKI:
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
      case PonderQuestionTransaction::TYPE_ANSWERWIKI:
        $object->setAnswerWiki($xaction->getNewValue());
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
      case PonderQuestionTransaction::TYPE_ANSWERWIKI:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldImplyCC(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_ANSWERS:
        return false;
    }

    return parent::shouldImplyCC($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
      foreach ($xactions as $xaction) {
        switch ($xaction->getTransactionType()) {
          case PonderQuestionTransaction::TYPE_ANSWERS:
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
          case PonderQuestionTransaction::TYPE_ANSWERS:
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
      if ($type == PonderQuestionTransaction::TYPE_CONTENT) {
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
