<?php

final class PonderQuestionEditor
  extends PhabricatorApplicationTransactionEditor {

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

  protected function supportsFeed() {
    return true;
  }

  protected function supportsSearch() {
    return true;
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

  protected function supportsMail() {
    return true;
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

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    // If the user just asked the question, add the question text.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      $old = $xaction->getOldValue();
      $new = $xaction->getNewValue();
      if ($type == PonderQuestionTransaction::TYPE_CONTENT) {
        if ($old === null) {
          $body->addRawSection($new);
        }
      }
    }

    $body->addTextSection(
      pht('QUESTION DETAIL'),
      PhabricatorEnv::getProductionURI('/Q'.$object->getID()));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return '[Ponder]';
  }

}
