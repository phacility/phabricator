<?php

abstract class ManiphestTaskAssignHeraldAction
  extends HeraldAction {

  const DO_ASSIGN = 'do.assign';

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  protected function applyAssign(array $phids) {
    $adapter = $this->getAdapter();
    $object = $adapter->getObject();

    $current = array($object->getOwnerPHID());

    $allowed_types = array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
    );

    $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
    if (!$targets) {
      return;
    }

    $phid = head_key($targets);

    $xaction = $adapter->newTransaction()
      ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
      ->setNewValue($phid);

    $adapter->queueTransaction($xaction);

    $this->logEffect(self::DO_ASSIGN, array($phid));
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_ASSIGN => array(
        'icon' => 'fa-user',
        'color' => 'green',
        'name' => pht('Assigned Task'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_ASSIGN:
        return pht(
          'Assigned task to: %s.',
          $this->renderHandleList($data));
    }
  }

}
