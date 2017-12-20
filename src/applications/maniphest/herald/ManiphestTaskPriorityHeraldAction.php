<?php

final class ManiphestTaskPriorityHeraldAction
  extends HeraldAction {

  const ACTIONCONST = 'maniphest.priority';
  const DO_PRIORITY = 'do.priority';

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  public function getHeraldActionName() {
    return pht('Change priority to');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $priority = head($effect->getTarget());

    if (!$priority) {
      $this->logEffect(self::DO_STANDARD_EMPTY);
      return;
    }

    $adapter = $this->getAdapter();
    $object = $adapter->getObject();
    $current = $object->getPriority();

    if ($current == $priority) {
      $this->logEffect(self::DO_STANDARD_NO_EFFECT, $priority);
      return;
    }

    $keyword_map = ManiphestTaskPriority::getTaskPriorityKeywordsMap();
    $keyword = head(idx($keyword_map, $priority));

    $xaction = $adapter->newTransaction()
      ->setTransactionType(ManiphestTaskPriorityTransaction::TRANSACTIONTYPE)
      ->setNewValue($keyword);

    $adapter->queueTransaction($xaction);
    $this->logEffect(self::DO_PRIORITY, $keyword);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  public function renderActionDescription($value) {
    $priority = head($value);
    $name = ManiphestTaskPriority::getTaskPriorityName($priority);
    return pht('Change priority to: %s.', $name);
  }

  protected function getDatasource() {
    return new ManiphestTaskPriorityDatasource();
  }

  protected function getDatasourceValueMap() {
    return ManiphestTaskPriority::getTaskPriorityMap();
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_PRIORITY => array(
        'icon' => 'fa-pencil',
        'color' => 'green',
        'name' => pht('Changed Task Priority'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_PRIORITY:
        return pht(
          'Changed task priority to "%s".',
          ManiphestTaskPriority::getTaskPriorityName($data));
    }
  }

}
