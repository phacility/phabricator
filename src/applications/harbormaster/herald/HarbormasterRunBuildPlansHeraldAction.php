<?php

final class HarbormasterRunBuildPlansHeraldAction
  extends HeraldAction {

  const DO_NO_TARGETS = 'do.no-targets';
  const DO_INVALID = 'do.invalid';
  const DO_BUILD = 'do.build';

  const ACTIONCONST = 'harbormaster.build';

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    $adapter = $this->getAdapter();
    return ($adapter instanceof HarbormasterBuildableAdapterInterface);
  }

  protected function applyBuilds(array $phids) {
    $adapter = $this->getAdapter();

    $phids = array_fuse($phids);
    if (!$phids) {
      $this->logEffect(self::DO_NO_TARGETS);
      return;
    }

    $plans = id(new HarbormasterBuildPlanQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->execute();
    $plans = mpull($plans, null, 'getPHID');

    $invalid = array();
    foreach ($phids as $phid) {
      if (empty($plans[$phid])) {
        $invalid[] = $phid;
        unset($plans[$phid]);
      }
    }

    if ($invalid) {
      $this->logEffect(self::DO_INVALID, $phids);
    }

    if (!$phids) {
      return;
    }

    foreach ($phids as $phid) {
      $adapter->queueHarbormasterBuildPlanPHID($phid);
    }

    $this->logEffect(self::DO_BUILD, $phids);
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_NO_TARGETS => array(
        'icon' => 'fa-ban',
        'color' => 'grey',
        'name' => pht('No Targets'),
      ),
      self::DO_INVALID => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('Invalid Targets'),
      ),
      self::DO_ALREADY_REQUIRED => array(
        'icon' => 'fa-play',
        'color' => 'green',
        'name' => pht('Building'),
      ),
    );
  }

  public function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_NO_TARGETS:
        return pht('Rule lists no targets.');
      case self::DO_INVALID:
        return pht(
          '%s build plan(s) are not valid: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_REQUIRED:
        return pht(
          'Started %s build(s): %s.',
          new PhutilNumber(count($data)),
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
    return $this->applyBuilds($effect->getTarget());
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
