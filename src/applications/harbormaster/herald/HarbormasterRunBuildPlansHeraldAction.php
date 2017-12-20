<?php

final class HarbormasterRunBuildPlansHeraldAction
  extends HeraldAction {

  const DO_BUILD = 'do.build';

  const ACTIONCONST = 'harbormaster.build';

  public function getRequiredAdapterStates() {
    return array(
      HeraldBuildableState::STATECONST,
    );
  }

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    $adapter = $this->getAdapter();
    return ($adapter instanceof HarbormasterBuildableAdapterInterface);
  }

  protected function applyBuilds(array $phids, HeraldRule $rule) {
    $adapter = $this->getAdapter();

    $allowed_types = array(
      HarbormasterBuildPlanPHIDType::TYPECONST,
    );

    $targets = $this->loadStandardTargets($phids, $allowed_types, array());
    if (!$targets) {
      return;
    }

    $phids = array_fuse(array_keys($targets));

    foreach ($phids as $phid) {
      $request = id(new HarbormasterBuildRequest())
        ->setBuildPlanPHID($phid)
        ->setInitiatorPHID($rule->getPHID());
      $adapter->queueHarbormasterBuildRequest($request);
    }

    $this->logEffect(self::DO_BUILD, $phids);
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_BUILD => array(
        'icon' => 'fa-play',
        'color' => 'green',
        'name' => pht('Building'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_BUILD:
        return pht(
          'Started %s build(s): %s.',
          phutil_count($data),
          $this->renderHandleList($data));
    }
  }

  public function getHeraldActionName() {
    return pht('Run build plans');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyBuilds($effect->getTarget(), $effect->getRule());
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new HarbormasterBuildPlanDatasource();
  }

  public function renderActionDescription($value) {
    return pht(
      'Run build plans: %s.',
      $this->renderHandleList($value));
  }
}
