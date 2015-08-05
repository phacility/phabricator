<?php

final class PhabricatorPasteEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPasteApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Pastes');
  }

  public static function initializeFileForPaste(
    PhabricatorUser $actor,
    $name,
    $data) {

    return PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $name,
        'mime-type' => 'text/plain; charset=utf-8',
        'authorPHID' => $actor->getPHID(),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
        'editPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorPasteTransaction::TYPE_CONTENT;
    $types[] = PhabricatorPasteTransaction::TYPE_TITLE;
    $types[] = PhabricatorPasteTransaction::TYPE_LANGUAGE;
    $types[] = PhabricatorPasteTransaction::TYPE_STATUS;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CONTENT:
        return $object->getFilePHID();
      case PhabricatorPasteTransaction::TYPE_TITLE:
        return $object->getTitle();
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        return $object->getLanguage();
      case PhabricatorPasteTransaction::TYPE_STATUS:
        return $object->getStatus();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CONTENT:
      case PhabricatorPasteTransaction::TYPE_TITLE:
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
      case PhabricatorPasteTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CONTENT:
        $object->setFilePHID($xaction->getNewValue());
        return;
      case PhabricatorPasteTransaction::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        return;
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        $object->setLanguage($xaction->getNewValue());
        return;
      case PhabricatorPasteTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CONTENT:
      case PhabricatorPasteTransaction::TYPE_TITLE:
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
      case PhabricatorPasteTransaction::TYPE_STATUS:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CONTENT:
        return array($xaction->getNewValue());
    }

    return parent::extractFilePHIDsFromCustomTransaction($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorPasteTransaction::TYPE_CONTENT:
          return false;
        default:
          break;
      }
    }
    return true;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.paste.subject-prefix');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->getActingAsPHID(),
    );
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorPasteTransaction::MAILTAG_CONTENT =>
        pht('Paste title, language or text changes.'),
      PhabricatorPasteTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a paste.'),
      PhabricatorPasteTransaction::MAILTAG_OTHER =>
        pht('Other paste activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PasteReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("P{$id}: {$name}")
      ->addHeader('Thread-Topic', "P{$id}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addLinkSection(
      pht('PASTE DETAIL'),
      PhabricatorEnv::getProductionURI('/P'.$object->getID()));

    return $body;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return false;
  }

}
