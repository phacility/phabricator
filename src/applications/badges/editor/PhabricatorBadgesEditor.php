<?php

final class PhabricatorBadgesEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Badges');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this badge.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorBadgesTransaction::MAILTAG_DETAILS =>
        pht('Someone changes the badge\'s details.'),
      PhabricatorBadgesTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a badge.'),
      PhabricatorBadgesTransaction::MAILTAG_OTHER =>
        pht('Other badge activity not listed above occurs.'),
    );
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $actor = $this->getActor();
    $actor_phid = $actor->getPHID();

    $results = parent::expandTransactions($object, $xactions);

    // Automatically subscribe the author when they create a badge.
    if ($this->getIsNewObject()) {
      if ($actor_phid) {
        $results[] = id(new PhabricatorBadgesTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(
            array(
              '+' => array($actor_phid => $actor_phid),
            ));
      }
    }

    return $results;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorBadgesReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();
    $id = $object->getID();
    $name = pht('Badge %d', $id);
    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $name);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getCreatorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $description = $object->getDescription();
    $body = parent::buildMailBody($object, $xactions);

    if (strlen($description)) {
      $body->addRemarkupSection(
        pht('BADGE DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('BADGE DETAIL'),
      PhabricatorEnv::getProductionURI('/badges/view/'.$object->getID().'/'));
    return $body;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Badge]');
  }

}
