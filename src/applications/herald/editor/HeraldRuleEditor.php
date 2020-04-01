<?php

final class HeraldRuleEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Herald Rules');
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return id(new HeraldRuleAdapter())
      ->setRule($object);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    return $types;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();

    $phids[] = $this->getActingAsPHID();

    if ($object->isPersonalRule()) {
      $phids[] = $object->getAuthorPHID();
    }

    return $phids;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new HeraldRuleReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $monogram = $object->getMonogram();
    $name = $object->getName();

    $subject = pht('%s: %s', $monogram, $name);

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject);
  }

  protected function getMailSubjectPrefix() {
    return pht('[Herald]');
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addLinkSection(
      pht('RULE DETAIL'),
      PhabricatorEnv::getProductionURI($object->getURI()));

    return $body;
  }

  protected function supportsSearch() {
    return true;
  }

}
