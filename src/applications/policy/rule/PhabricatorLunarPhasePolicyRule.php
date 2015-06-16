<?php

final class PhabricatorLunarPhasePolicyRule extends PhabricatorPolicyRule {

  const PHASE_FULL = 'full';
  const PHASE_NEW = 'new';
  const PHASE_WAXING = 'waxing';
  const PHASE_WANING = 'waning';

  public function getRuleDescription() {
    return pht('when the moon');
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $moon = new PhutilLunarPhase(PhabricatorTime::getNow());

    switch ($value) {
      case 'full':
        return $moon->isFull();
      case 'new':
        return $moon->isNew();
      case 'waxing':
        return $moon->isWaxing();
      case 'waning':
        return $moon->isWaning();
    }

    return false;
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_SELECT;
  }

  public function getValueControlTemplate() {
    return array(
      'options' => array(
        self::PHASE_FULL => pht('is full'),
        self::PHASE_NEW => pht('is new'),
        self::PHASE_WAXING => pht('is waxing'),
        self::PHASE_WANING => pht('is waning'),
      ),
    );
  }

  public function getRuleOrder() {
    return 1000;
  }

}
