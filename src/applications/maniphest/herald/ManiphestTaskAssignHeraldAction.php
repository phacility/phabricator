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

    if (head($phids) == PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN) {
      $phid = null;

      if ($object->getOwnerPHID() == null) {
        $this->logEffect(self::DO_STANDARD_NO_EFFECT);
        return;
      }
    } else {
      $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
      if (!$targets) {
        return;
      }

      $phid = head_key($targets);
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(ManiphestTaskOwnerTransaction::TRANSACTIONTYPE)
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
        if (head($data) === null) {
          return pht('Unassigned task.');
        } else {
          return pht(
            'Assigned task to: %s.',
            $this->renderHandleList($data));
        }
    }
  }

}
