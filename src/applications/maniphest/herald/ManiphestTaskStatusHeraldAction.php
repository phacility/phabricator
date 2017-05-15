<?php

final class ManiphestTaskStatusHeraldAction
  extends HeraldAction {

  const ACTIONCONST = 'maniphest.status';
  const DO_STATUS = 'do.status';

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  public function getHeraldActionName() {
    return pht('Change status to');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $status = head($effect->getTarget());

    if (!$status) {
      $this->logEffect(self::DO_STANDARD_EMPTY);
      return;
    }

    $adapter = $this->getAdapter();
    $object = $adapter->getObject();
    $current = $object->getStatus();

    if ($current == $status) {
      $this->logEffect(self::DO_STANDARD_NO_EFFECT, $status);
      return;
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(ManiphestTaskStatusTransaction::TRANSACTIONTYPE)
      ->setNewValue($status);

    $adapter->queueTransaction($xaction);
    $this->logEffect(self::DO_STATUS, $status);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  public function renderActionDescription($value) {
    $status = head($value);
    $name = ManiphestTaskStatus::getTaskStatusName($status);
    return pht('Change status to: %s.', $name);
  }

  protected function getDatasource() {
    return new ManiphestTaskStatusDatasource();
  }

  protected function getDatasourceValueMap() {
    return ManiphestTaskStatus::getTaskStatusMap();
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_STATUS => array(
        'icon' => 'fa-pencil',
        'color' => 'green',
        'name' => pht('Changed Task Status'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_STATUS:
        return pht(
          'Changed task status to "%s".',
          ManiphestTaskStatus::getTaskStatusName($data));
    }
  }

}
