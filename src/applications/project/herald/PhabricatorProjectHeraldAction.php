<?php

abstract class PhabricatorProjectHeraldAction
  extends HeraldAction {

  const DO_NO_TARGETS = 'do.no-targets';
  const DO_INVALID = 'do.invalid';
  const DO_ALREADY_ASSOCIATED = 'do.already-associated';
  const DO_ALREADY_UNASSOCIATED = 'do.already-unassociated';
  const DO_ADD_PROJECTS = 'do.add-projects';
  const DO_REMOVE_PROJECTS = 'do.remove-projects';

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL);
  }

  protected function applyProjects(array $phids, $is_add) {
    $phids = array_fuse($phids);
    $adapter = $this->getAdapter();

    if (!$phids) {
      $this->logEffect(self::DO_NO_TARGETS);
      return;
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->execute();
    $projects = mpull($projects, null, 'getPHID');

    $invalid = array();
    foreach ($phids as $phid) {
      if (empty($projects[$phid])) {
        $invalid[] = $phid;
        unset($phids[$phid]);
      }
    }

    if ($invalid) {
      $this->logEffect(self::DO_INVALID, $invalid);
    }

    if (!$phids) {
      return;
    }

    $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

    $current = $adapter->loadEdgePHIDs($project_type);

    if ($is_add) {
      $already = array();
      foreach ($phids as $phid) {
        if (isset($current[$phid])) {
          $already[$phid] = $phid;
          unset($phids[$phid]);
        }
      }

      if ($already) {
        $this->logEffect(self::DO_ALREADY_ASSOCIATED, $already);
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
        $this->logEffect(self::DO_ALREADY_UNASSOCIATED, $already);
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
      ->setMetadataValue('edge:type', $project_type)
      ->setNewValue(
        array(
          $kind => $phids,
        ));

    $adapter->queueTransaction($xaction);

    if ($is_add) {
      $this->logEffect(self::DO_ADD_PROJECTS, $phids);
    } else {
      $this->logEffect(self::DO_REMOVE_PROJECTS, $phids);
    }
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
      self::DO_ALREADY_ASSOCIATED => array(
        'icon' => 'fa-chevron-right',
        'color' => 'grey',
        'name' => pht('Already Associated'),
      ),
      self::DO_ALREADY_UNASSOCIATED => array(
        'icon' => 'fa-chevron-right',
        'color' => 'grey',
        'name' => pht('Already Unassociated'),
      ),
      self::DO_ADD_PROJECTS => array(
        'icon' => 'fa-briefcase',
        'color' => 'green',
        'name' => pht('Added Projects'),
      ),
      self::DO_REMOVE_PROJECTS => array(
        'icon' => 'fa-minus-circle',
        'color' => 'green',
        'name' => pht('Removed Projects'),
      ),
    );
  }

  public function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_NO_TARGETS:
        return pht('Rule lists no projects.');
      case self::DO_INVALID:
        return pht(
          'Declined to act on %s invalid project(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ALREADY_ASSOCIATED:
        return pht(
          '%s project(s) are already associated: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ALREADY_UNASSOCIATED:
        return pht(
          '%s project(s) are not associated: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ADD_PROJECTS:
        return pht(
          'Added %s project(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_REMOVE_PROJECTS:
        return pht(
          'Removed %s project(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
    }
  }

}
