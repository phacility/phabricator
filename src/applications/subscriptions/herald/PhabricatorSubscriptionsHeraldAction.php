<?php

abstract class PhabricatorSubscriptionsHeraldAction
  extends HeraldAction {

  const DO_PREVIOUSLY_UNSUBSCRIBED = 'do.previously-unsubscribed';
  const DO_AUTOSUBSCRIBED = 'do.autosubscribed';
  const DO_SUBSCRIBED = 'do.subscribed';
  const DO_UNSUBSCRIBED = 'do.unsubscribed';

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  protected function applySubscribe(array $phids, $is_add) {
    $adapter = $this->getAdapter();

    $allowed_types = array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
      PhabricatorProjectProjectPHIDType::TYPECONST,
    );

    // Evaluating "No Effect" is a bit tricky for this rule type, so just
    // do it manually below.
    $current = array();

    $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
    if (!$targets) {
      return;
    }

    $phids = array_fuse(array_keys($targets));

    // The "Add Subscribers" rule only adds subscribers who haven't previously
    // unsubscribed from the object explicitly. Filter these subscribers out
    // before continuing.
    if ($is_add) {
      $unsubscribed = $adapter->loadEdgePHIDs(
        PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST);

      foreach ($unsubscribed as $phid) {
        if (isset($phids[$phid])) {
          $unsubscribed[$phid] = $phid;
          unset($phids[$phid]);
        }
      }

      if ($unsubscribed) {
        $this->logEffect(
          self::DO_PREVIOUSLY_UNSUBSCRIBED,
          array_values($unsubscribed));
      }
    }

    if (!$phids) {
      return;
    }

    $auto = array();
    $object = $adapter->getObject();
    foreach ($phids as $phid) {
      if ($object->isAutomaticallySubscribed($phid)) {
        $auto[$phid] = $phid;
        unset($phids[$phid]);
      }
    }

    if ($auto) {
      $this->logEffect(self::DO_AUTOSUBSCRIBED, array_values($auto));
    }

    if (!$phids) {
      return;
    }

    $current = $adapter->loadEdgePHIDs(
      PhabricatorObjectHasSubscriberEdgeType::EDGECONST);

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
      ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
      ->setNewValue(
        array(
          $kind => $phids,
        ));

    $adapter->queueTransaction($xaction);

    if ($is_add) {
      $this->logEffect(self::DO_SUBSCRIBED, $phids);
    } else {
      $this->logEffect(self::DO_UNSUBSCRIBED, $phids);
    }
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_PREVIOUSLY_UNSUBSCRIBED => array(
        'icon' => 'fa-minus-circle',
        'color' => 'grey',
        'name' => pht('Previously Unsubscribed'),
      ),
      self::DO_AUTOSUBSCRIBED => array(
        'icon' => 'fa-envelope',
        'color' => 'grey',
        'name' => pht('Automatically Subscribed'),
      ),
      self::DO_SUBSCRIBED => array(
        'icon' => 'fa-envelope',
        'color' => 'green',
        'name' => pht('Added Subscribers'),
      ),
      self::DO_UNSUBSCRIBED => array(
        'icon' => 'fa-minus-circle',
        'color' => 'green',
        'name' => pht('Removed Subscribers'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_PREVIOUSLY_UNSUBSCRIBED:
        return pht(
          'Declined to resubscribe %s target(s) because they previously '.
          'unsubscribed: %s.',
          phutil_count($data),
          $this->renderHandleList($data));
      case self::DO_AUTOSUBSCRIBED:
        return pht(
          '%s automatically subscribed target(s) were not affected: %s.',
          phutil_count($data),
          $this->renderHandleList($data));
      case self::DO_SUBSCRIBED:
        return pht(
          'Added %s subscriber(s): %s.',
          phutil_count($data),
          $this->renderHandleList($data));
      case self::DO_UNSUBSCRIBED:
        return pht(
          'Removed %s subscriber(s): %s.',
          phutil_count($data),
          $this->renderHandleList($data));
    }
  }


}
