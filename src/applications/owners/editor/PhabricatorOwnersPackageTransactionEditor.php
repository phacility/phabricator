<?php

final class PhabricatorOwnersPackageTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Owners Packages');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Package]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $this->requireActor()->getPHID(),
    );
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return mpull($object->getOwners(), 'getUserPHID');
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new OwnersPackageReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $detail_uri = PhabricatorEnv::getProductionURI($object->getURI());

    $body->addLinkSection(
      pht('PACKAGE DETAIL'),
      $detail_uri);

    return $body;
  }

  protected function supportsSearch() {
    return true;
  }

}
