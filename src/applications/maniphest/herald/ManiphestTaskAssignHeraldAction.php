<?php

abstract class ManiphestTaskAssignHeraldAction
  extends HeraldAction {

  const DO_EMPTY = 'do.send';
  const DO_ALREADY = 'do.already';
  const DO_INVALID = 'do.invalid';
  const DO_PERMISSION = 'do.permission';
  const DO_ASSIGN = 'do.assign';

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  protected function applyAssign(array $phids) {
    $phid = head($phids);

    if (!$phid) {
      $this->logEffect(self::DO_EMPTY);
      return;
    }

    $adapter = $this->getAdapter();
    $object = $adapter->getObject();

    if ($object->getOwnerPHID() == $phid) {
      $this->logEffect(self::DO_ALREADY, array($phid));
      return;
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$user) {
      $this->logEffect(self::DO_INVALID, array($phid));
      return;
    }

    $can_view = PhabricatorPolicyFilter::hasCapability(
      $user,
      $object,
      PhabricatorPolicyCapability::CAN_VIEW);
    if (!$can_view) {
      $this->logEffect(self::DO_PERMISSION, array($phid));
      return;
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
      ->setNewValue($phid);

    $adapter->queueTransaction($xaction);

    $this->logEffect(self::DO_ASSIGN, array($phid));
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_EMPTY => array(
        'icon' => 'fa-ban',
        'color' => 'grey',
        'name' => pht('Empty Action'),
      ),
      self::DO_ALREADY => array(
        'icon' => 'fa-user',
        'color' => 'grey',
        'name' => pht('Already Assigned'),
      ),
      self::DO_INVALID => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('Invalid Owner'),
      ),
      self::DO_PERMISSION => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('No Permission'),
      ),
      self::DO_ASSIGN => array(
        'icon' => 'fa-user',
        'color' => 'green',
        'name' => pht('Assigned Task'),
      ),
    );
  }

  public function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_EMPTY:
        return pht('Action lists no user to assign.');
      case self::DO_ALREADY:
        return pht(
          'User is already task owner: %s.',
          $this->renderHandleList($data));
      case self::DO_INVALID:
        return pht(
          'User is invalid: %s.',
          $this->renderHandleList($data));
      case self::DO_PERMISSION:
        return pht(
          'User does not have permission to see task: %s.',
          $this->renderHandleList($data));
      case self::DO_ASSIGN:
        return pht(
          'Assigned task to: %s.',
          $this->renderHandleList($data));
    }
  }

}
