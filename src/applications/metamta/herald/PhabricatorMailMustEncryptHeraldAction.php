<?php

final class PhabricatorMailMustEncryptHeraldAction
  extends HeraldAction {

  const DO_MUST_ENCRYPT = 'do.must-encrypt';

  const ACTIONCONST = 'email.must-encrypt';

  public function getHeraldActionName() {
    return pht('Require secure email');
  }

  public function renderActionDescription($value) {
    return pht(
      'Require mail content be transmitted only over secure channels.');
  }
  public function supportsObject($object) {
    return PhabricatorMetaMTAEmailHeraldAction::isMailGeneratingObject($object);
  }

  public function getActionGroupKey() {
    return HeraldUtilityActionGroup::ACTIONGROUPKEY;
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $rule_phid = $effect->getRule()->getPHID();

    $adapter = $this->getAdapter();
    $adapter->addMustEncryptReason($rule_phid);

    $this->logEffect(self::DO_MUST_ENCRYPT, array($rule_phid));
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_MUST_ENCRYPT => array(
        'icon' => 'fa-shield',
        'color' => 'blue',
        'name' => pht('Must Encrypt'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_MUST_ENCRYPT:
        return pht(
          'Made it a requirement that mail content be transmitted only '.
          'over secure channels.');
    }
  }

}
