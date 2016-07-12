<?php

final class PhabricatorSlowvoteEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorSlowvoteApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Slowvote');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;

    $types[] = PhabricatorSlowvoteTransaction::TYPE_QUESTION;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_RESPONSES;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_SHUFFLE;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_CLOSE;

    return $types;
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
        if ($old === null) {
          return true;
        }
        return ((int)$old !== (int)$new);
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
        if ($old === null) {
          return true;
        }
        return ((bool)$old !== (bool)$new);
    }

    return parent::transactionHasEffect($object, $xaction);
  }


  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_QUESTION:
        return $object->getQuestion();
      case PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
        return $object->getResponseVisibility();
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
        return $object->getShuffle();
      case PhabricatorSlowvoteTransaction::TYPE_CLOSE:
        return $object->getIsClosed();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_QUESTION:
      case PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION:
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
      case PhabricatorSlowvoteTransaction::TYPE_CLOSE:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_QUESTION:
        $object->setQuestion($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
        $object->setResponseVisibility($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
        $object->setShuffle($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_CLOSE:
        $object->setIsClosed((int)$xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

    protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorSlowvoteTransaction::MAILTAG_DETAILS =>
        pht('Someone changes the poll details.'),
      PhabricatorSlowvoteTransaction::MAILTAG_RESPONSES =>
        pht('Someone votes on a poll.'),
      PhabricatorSlowvoteTransaction::MAILTAG_OTHER =>
        pht('Other poll activity not listed above occurs.'),
    );
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $monogram = $object->getMonogram();
    $name = $object->getQuestion();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("{$monogram}: {$name}")
      ->addHeader('Thread-Topic', $monogram);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $description = $object->getDescription();

    if (strlen($description)) {
      $body->addRemarkupSection(
        pht('SLOWVOTE DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('SLOWVOTE DETAIL'),
      PhabricatorEnv::getProductionURI('/'.$object->getMonogram()));

    return $body;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }
  protected function getMailSubjectPrefix() {
    return '[Slowvote]';
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorSlowvoteReplyHandler())
      ->setMailReceiver($object);
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

}
