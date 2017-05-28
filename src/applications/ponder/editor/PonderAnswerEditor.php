<?php

final class PonderAnswerEditor extends PonderEditor {

  public function getEditorObjectsDescription() {
    return pht('Ponder Answers');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s added this answer.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s added %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
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
      if ($type == PonderAnswerContentTransaction::TRANSACTIONTYPE) {
        $body->addRawSection($new);
      }
    }

    $body->addLinkSection(
      pht('ANSWER DETAIL'),
      PhabricatorEnv::getProductionURI($object->getURI()));

    return $body;
  }

}
