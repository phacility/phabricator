<?php

final class PonderAnswerEditor extends PonderEditor {

  public function getEditorObjectsDescription() {
    return pht('Ponder Answers');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PonderAnswerTransaction::TYPE_CONTENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderAnswerTransaction::TYPE_CONTENT:
        return $object->getContent();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderAnswerTransaction::TYPE_CONTENT:
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

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    $question = $object->getQuestion();
    return id(new PonderQuestionReplyHandler())
      ->setMailReceiver($question);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $question = $object->getQuestion();
    return parent::buildMailTemplate($question);
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
