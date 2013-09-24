<?php

final class ManiphestTransactionEditorPro
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = ManiphestTransaction::TYPE_PRIORITY;
    $types[] = ManiphestTransaction::TYPE_STATUS;
    $types[] = ManiphestTransaction::TYPE_TITLE;
    $types[] = ManiphestTransaction::TYPE_DESCRIPTION;
    $types[] = ManiphestTransaction::TYPE_OWNER;
    $types[] = ManiphestTransaction::TYPE_CCS;
    $types[] = ManiphestTransaction::TYPE_PROJECTS;
    $types[] = ManiphestTransaction::TYPE_ATTACH;
    $types[] = ManiphestTransaction::TYPE_EDGE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        return (int)$object->getPriority();
      case ManiphestTransaction::TYPE_STATUS:
        return (int)$object->getStatus();
      case ManiphestTransaction::TYPE_TITLE:
        return $object->getTitle();
      case ManiphestTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case ManiphestTransaction::TYPE_OWNER:
        return $object->getOwnerPHID();
      case ManiphestTransaction::TYPE_CCS:
        return array_values(array_unique($object->getCCPHIDs()));
      case ManiphestTransaction::TYPE_PROJECTS:
        return $object->getProjectPHIDs();
      case ManiphestTransaction::TYPE_ATTACH:
        return $object->getAttached();
      case ManiphestTransaction::TYPE_EDGE:
        // These are pre-populated.
        return $xaction->getOldValue();
    }

  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
      case ManiphestTransaction::TYPE_STATUS:
        return (int)$xaction->getNewValue();
      case ManiphestTransaction::TYPE_CCS:
        return array_values(array_unique($xaction->getNewValue()));
      case ManiphestTransaction::TYPE_TITLE:
      case ManiphestTransaction::TYPE_DESCRIPTION:
      case ManiphestTransaction::TYPE_OWNER:
      case ManiphestTransaction::TYPE_PROJECTS:
      case ManiphestTransaction::TYPE_ATTACH:
      case ManiphestTransaction::TYPE_EDGE:
        return $xaction->getNewValue();
    }

  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        return $object->setPriority($xaction->getNewValue());
      case ManiphestTransaction::TYPE_STATUS:
        return $object->setStatus($xaction->getNewValue());
      case ManiphestTransaction::TYPE_TITLE:
        return $object->setTitle($xaction->getNewValue());
      case ManiphestTransaction::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
      case ManiphestTransaction::TYPE_OWNER:
        return $object->setOwnerPHID($xaction->getNewValue());
      case ManiphestTransaction::TYPE_CCS:
        return $object->setCCPHIDs($xaction->getNewValue());
      case ManiphestTransaction::TYPE_PROJECTS:
        return $object->setProjectPHIDs($xaction->getNewValue());
      case ManiphestTransaction::TYPE_ATTACH:
        return $object->setAttached($xaction->getNewValue());
      case ManiphestTransaction::TYPE_EDGE:
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
