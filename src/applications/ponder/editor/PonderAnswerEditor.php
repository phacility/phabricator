<?php

final class PonderAnswerEditor extends PonderEditor {

  public function getEditorObjectsDescription() {
    return pht('Ponder Answers');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;

    $types[] = PonderAnswerTransaction::TYPE_CONTENT;
    $types[] = PonderAnswerTransaction::TYPE_STATUS;
    $types[] = PonderAnswerTransaction::TYPE_QUESTION_ID;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderAnswerTransaction::TYPE_CONTENT:
      case PonderAnswerTransaction::TYPE_STATUS:
        return $object->getContent();
      case PonderAnswerTransaction::TYPE_QUESTION_ID:
        return $object->getQuestionID();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderAnswerTransaction::TYPE_CONTENT:
      case PonderAnswerTransaction::TYPE_STATUS:
      case PonderAnswerTransaction::TYPE_QUESTION_ID:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderAnswerTransaction::TYPE_CONTENT:
        $object->setContent($xaction->getNewValue());
        break;
      case PonderAnswerTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        break;
      case PonderAnswerTransaction::TYPE_QUESTION_ID:
        $object->setQuestionID($xaction->getNewValue());
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
      case PonderAnswerTransaction::TYPE_CONTENT:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $object->getAuthorPHID();
    $phids[] = $this->requireActor()->getPHID();

    $question = id(new PonderQuestionQuery())
      ->setViewer($this->requireActor())
      ->withIDs(array($object->getQuestionID()))
      ->executeOne();

    $phids[] = $question->getAuthorPHID();

    return $phids;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
      return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PonderAnswerReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("ANSR{$id}")
      ->addHeader('Thread-Topic', "ANSR{$id}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    // If the user just gave the answer, add the answer text.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      $new = $xaction->getNewValue();
      if ($type == PonderAnswerTransaction::TYPE_CONTENT) {
        $body->addRawSection($new);
      }
    }

    $body->addLinkSection(
      pht('ANSWER DETAIL'),
      PhabricatorEnv::getProductionURI($object->getURI()));

    return $body;
  }

}
