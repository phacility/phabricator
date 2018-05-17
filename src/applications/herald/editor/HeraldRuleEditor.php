<?php

final class HeraldRuleEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Herald Rules');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = HeraldRuleTransaction::TYPE_EDIT;
    $types[] = HeraldRuleTransaction::TYPE_NAME;
    $types[] = HeraldRuleTransaction::TYPE_DISABLE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_DISABLE:
        return (int)$object->getIsDisabled();
      case HeraldRuleTransaction::TYPE_EDIT:
        return id(new HeraldRuleSerializer())
          ->serializeRule($object);
      case HeraldRuleTransaction::TYPE_NAME:
        return $object->getName();
    }

  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_DISABLE:
        return (int)$xaction->getNewValue();
      case HeraldRuleTransaction::TYPE_EDIT:
      case HeraldRuleTransaction::TYPE_NAME:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_DISABLE:
        return $object->setIsDisabled($xaction->getNewValue());
      case HeraldRuleTransaction::TYPE_NAME:
        return $object->setName($xaction->getNewValue());
      case HeraldRuleTransaction::TYPE_EDIT:
        $new_state = id(new HeraldRuleSerializer())
          ->deserializeRuleComponents($xaction->getNewValue());
        $object->setMustMatchAll((int)$new_state['match_all']);
        $object->attachConditions($new_state['conditions']);
        $object->attachActions($new_state['actions']);

        $new_repetition = $new_state['repetition_policy'];
        $object->setRepetitionPolicyStringConstant($new_repetition);

        return $object;
    }

  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HeraldRuleTransaction::TYPE_EDIT:
        $object->saveConditions($object->getConditions());
        $object->saveActions($object->getActions());
        break;
    }
    return;
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

}
