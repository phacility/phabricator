<?php

final class ManiphestTransactionEditorPro
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = ManiphestTransactionPro::TYPE_PRIORITY;
    $types[] = ManiphestTransactionPro::TYPE_STATUS;
    $types[] = ManiphestTransactionPro::TYPE_TITLE;
    $types[] = ManiphestTransactionPro::TYPE_DESCRIPTION;
    $types[] = ManiphestTransactionPro::TYPE_OWNER;
    $types[] = ManiphestTransactionPro::TYPE_CCS;
    $types[] = ManiphestTransactionPro::TYPE_PROJECTS;
    $types[] = ManiphestTransactionPro::TYPE_ATTACH;
    $types[] = ManiphestTransactionPro::TYPE_EDGE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransactionPro::TYPE_PRIORITY:
        return (int)$object->getPriority();
      case ManiphestTransactionPro::TYPE_STATUS:
        return (int)$object->getStatus();
      case ManiphestTransactionPro::TYPE_TITLE:
        return $object->getTitle();
      case ManiphestTransactionPro::TYPE_DESCRIPTION:
        return $object->getDescription();
      case ManiphestTransactionPro::TYPE_OWNER:
        return $object->getOwnerPHID();
      case ManiphestTransactionPro::TYPE_CCS:
        return array_values(array_unique($object->getCCPHIDs()));
      case ManiphestTransactionPro::TYPE_PROJECTS:
        return $object->getProjectPHIDs();
      case ManiphestTransactionPro::TYPE_ATTACH:
        return $object->getAttached();
      case ManiphestTransactionPro::TYPE_EDGE:
        // These are pre-populated.
        return $xaction->getOldValue();
    }

  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransactionPro::TYPE_PRIORITY:
      case ManiphestTransactionPro::TYPE_STATUS:
        return (int)$xaction->getNewValue();
      case ManiphestTransactionPro::TYPE_CCS:
        return array_values(array_unique($xaction->getNewValue()));
      case ManiphestTransactionPro::TYPE_TITLE:
      case ManiphestTransactionPro::TYPE_DESCRIPTION:
      case ManiphestTransactionPro::TYPE_OWNER:
      case ManiphestTransactionPro::TYPE_PROJECTS:
      case ManiphestTransactionPro::TYPE_ATTACH:
      case ManiphestTransactionPro::TYPE_EDGE:
        return $xaction->getNewValue();
    }

  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransactionPro::TYPE_PRIORITY:
        return $object->setPriority($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_STATUS:
        return $object->setStatus($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_TITLE:
        return $object->setTitle($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_OWNER:
        return $object->setOwnerPHID($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_CCS:
        return $object->setCCPHIDs($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_PROJECTS:
        return $object->setProjectPHIDs($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_ATTACH:
        return $object->setAttached($xaction->getNewValue());
      case ManiphestTransactionPro::TYPE_EDGE:
        // These are a weird, funky mess and are already being applied by the
        // time we reach this.
        return;
    }

  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.maniphest.subject-prefix');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    return 'maniphest-task-'.$object->getPHID();
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getOwnerPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return $object->getCCPHIDs();
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new ManiphestReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("T{$id}: {$title}")
      ->addHeader('Thread-Topic', "T{$id}: ".$object->getOriginalTitle());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addTextSection(
      pht('TASK DETAIL'),
      PhabricatorEnv::getProductionURI('/T'.$object->getID()));

    return $body;
  }

  protected function supportsFeed() {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

}
