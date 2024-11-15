<?php

abstract class PhabricatorRoleHeraldAction
  extends HeraldAction {

  const DO_ADD_ROLES = 'do.add-roles';
  const DO_REMOVE_ROLES = 'do.remove-roles';

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL);
  }

  protected function applyRoles(array $phids, $is_add) {
    $adapter = $this->getAdapter();

    $allowed_types = array(
      PhabricatorRoleRolePHIDType::TYPECONST,
    );

    // Detection of "No Effect" is a bit tricky for this action, so just do it
    // manually a little later on.
    $current = array();

    $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
    if (!$targets) {
      return;
    }

    $phids = array_fuse(array_keys($targets));

    $role_type = PhabricatorRoleObjectHasRoleEdgeType::EDGECONST;
    $current = $adapter->loadEdgePHIDs($role_type);

    if ($is_add) {
      $already = array();
      foreach ($phids as $phid) {
        if (isset($current[$phid])) {
          $already[$phid] = $phid;
          unset($phids[$phid]);
        }
      }

      if ($already) {
        $this->logEffect(self::DO_STANDARD_NO_EFFECT, $already);
      }
    } else {
      $already = array();
      foreach ($phids as $phid) {
        if (empty($current[$phid])) {
          $already[$phid] = $phid;
          unset($phids[$phid]);
        }
      }

      if ($already) {
        $this->logEffect(self::DO_STANDARD_NO_EFFECT, $already);
      }
    }

    if (!$phids) {
      return;
    }

    if ($is_add) {
      $kind = '+';
    } else {
      $kind = '-';
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $role_type)
      ->setNewValue(
        array(
          $kind => $phids,
        ));

    $adapter->queueTransaction($xaction);

    if ($is_add) {
      $this->logEffect(self::DO_ADD_ROLES, $phids);
    } else {
      $this->logEffect(self::DO_REMOVE_ROLES, $phids);
    }
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_ADD_ROLES => array(
        'icon' => 'fa-briefcase',
        'color' => 'green',
        'name' => pht('Added Roles'),
      ),
      self::DO_REMOVE_ROLES => array(
        'icon' => 'fa-minus-circle',
        'color' => 'green',
        'name' => pht('Removed Roles'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_ADD_ROLES:
        return pht(
          'Added %s role(s): %s.',
          phutil_count($data),
          $this->renderHandleList($data));
      case self::DO_REMOVE_ROLES:
        return pht(
          'Removed %s role(s): %s.',
          phutil_count($data),
          $this->renderHandleList($data));
    }
  }

}
