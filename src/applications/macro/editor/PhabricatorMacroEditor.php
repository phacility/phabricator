<?php

final class PhabricatorMacroEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorMacroTransactionType::TYPE_NAME;
    $types[] = PhabricatorMacroTransactionType::TYPE_DISABLED;
    $types[] = PhabricatorMacroTransactionType::TYPE_FILE;
    $types[] = PhabricatorMacroTransactionType::TYPE_AUDIO;
    $types[] = PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return $object->getName();
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        return $object->getIsDisabled();
      case PhabricatorMacroTransactionType::TYPE_FILE:
        return $object->getFilePHID();
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
        return $object->getAudioPHID();
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        return $object->getAudioBehavior();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
      case PhabricatorMacroTransactionType::TYPE_FILE:
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        $object->setIsDisabled($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_FILE:
        $object->setFilePHID($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
        $object->setAudioPHID($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        $object->setAudioBehavior($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_FILE:
        return array($xaction->getNewValue());
    }

    return array();
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
      case PhabricatorMacroTransactionType::TYPE_FILE:
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorMacroTransactionType::TYPE_NAME;
          return ($xaction->getOldValue() !== null);
        default:
          break;
      }
    }
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorMacroReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();
    $name = 'Image Macro "'.$name.'"';

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $name);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $body->addTextSection(
      pht('MACRO DETAIL'),
      PhabricatorEnv::getProductionURI('/macro/view/'.$object->getID().'/'));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.macro.subject-prefix');
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }
}
