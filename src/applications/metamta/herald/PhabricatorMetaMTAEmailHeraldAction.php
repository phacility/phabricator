<?php

abstract class PhabricatorMetaMTAEmailHeraldAction
  extends HeraldAction {

  const DO_SEND = 'do.send';
  const DO_FORCE = 'do.force';

  public function supportsObject($object) {
    // NOTE: This implementation lacks generality, but there's no great way to
    // figure out if something generates email right now.

    if ($object instanceof DifferentialDiff) {
      return false;
    }

    if ($object instanceof PhabricatorMetaMTAMail) {
      return false;
    }

    return true;
  }

  public function getActionGroupKey() {
    return HeraldNotifyActionGroup::ACTIONGROUPKEY;
  }

  protected function applyEmail(array $phids, $force) {
    $adapter = $this->getAdapter();

    $allowed_types = array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
      PhabricatorProjectProjectPHIDType::TYPECONST,
    );

    // There's no stateful behavior for this action: we always just send an
    // email.
    $current = array();

    $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
    if (!$targets) {
      return;
    }

    $phids = array_fuse(array_keys($targets));

    foreach ($phids as $phid) {
      $adapter->addEmailPHID($phid, $force);
    }

    if ($force) {
      $this->logEffect(self::DO_FORCE, $phids);
    } else {
      $this->logEffect(self::DO_SEND, $phids);
    }
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_SEND => array(
        'icon' => 'fa-envelope',
        'color' => 'green',
        'name' => pht('Sent Mail'),
      ),
      self::DO_FORCE => array(
        'icon' => 'fa-envelope',
        'color' => 'blue',
        'name' => pht('Forced Mail'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_SEND:
        return pht(
          'Queued email to be delivered to %s target(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_FORCE:
        return pht(
          'Queued email to be delivered to %s target(s), ignoring their '.
          'notification preferences: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
    }
  }

}
