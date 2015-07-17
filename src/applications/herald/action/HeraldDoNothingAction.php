<?php

final class HeraldDoNothingAction extends HeraldAction {

  const ACTIONCONST = 'nothing';
  const DO_NOTHING = 'do.nothing';

  public function getHeraldActionName() {
    return pht('Do nothing');
  }

  public function getActionGroupKey() {
    return HeraldUtilityActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return true;
  }

  public function supportsRuleType($rule_type) {
    return true;
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $this->logEffect($effect, self::DO_NOTHING);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

}
