<?php

abstract class DiffusionAuditorsHeraldAction
  extends HeraldAction {

  const DO_ADD_AUDITORS = 'do.add-auditors';

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorRepositoryCommit);
  }

  protected function applyAuditors(array $phids, HeraldRule $rule) {
    $adapter = $this->getAdapter();
    $object = $adapter->getObject();

    $auditors = $object->getAudits();
    $auditors = mpull($auditors, null, 'getAuditorPHID');
    $current = array_keys($auditors);

    $allowed_types = array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
      PhabricatorProjectProjectPHIDType::TYPECONST,
      PhabricatorOwnersPackagePHIDType::TYPECONST,
    );

    $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
    if (!$targets) {
      return;
    }

    $phids = array_fuse(array_keys($targets));

    // TODO: Convert this to be translatable, structured data eventually.
    $reason_map = array();
    foreach ($phids as $phid) {
      $reason_map[$phid][] = pht('%s Triggered Audit', $rule->getMonogram());
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(PhabricatorAuditActionConstants::ADD_AUDITORS)
      ->setNewValue($phids)
      ->setMetadataValue(
        'auditStatus',
        PhabricatorAuditStatusConstants::AUDIT_REQUIRED)
      ->setMetadataValue('auditReasonMap', $reason_map);

    $adapter->queueTransaction($xaction);

    $this->logEffect(self::DO_ADD_AUDITORS, $phids);
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_ADD_AUDITORS => array(
        'icon' => 'fa-user',
        'color' => 'green',
        'name' => pht('Added Auditors'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_ADD_AUDITORS:
        return pht(
          'Added %s auditor(s): %s.',
          phutil_count($data),
          $this->renderHandleList($data));
    }
  }

}
