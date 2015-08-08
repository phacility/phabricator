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
    $this->logEffect(self::DO_NOTHING);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_NOTHING => array(
        'icon' => 'fa-check',
        'color' => 'grey',
        'name' => pht('Did Nothing'),
      ),
    );
  }

  public function renderActionDescription($value) {
    return pht('Do nothing.');
  }

  protected function renderActionEffectDescription($type, $data) {
    return pht('Did nothing.');
  }

}
