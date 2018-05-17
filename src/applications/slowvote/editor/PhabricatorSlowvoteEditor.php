<?php

final class PhabricatorSlowvoteEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorSlowvoteApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Slowvote');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this poll.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;

    return $types;
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
      ->setSubject("{$monogram}: {$name}");
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
