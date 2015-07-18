<?php

abstract class PhabricatorSubscriptionsHeraldAction
  extends HeraldAction {

  const DO_NO_TARGETS = 'do.no-targets';
  const DO_PREVIOUSLY_UNSUBSCRIBED = 'do.previously-unsubscribed';
  const DO_INVALID = 'do.invalid';
  const DO_AUTOSUBSCRIBED = 'do.autosubscribed';
  const DO_ALREADY_SUBSCRIBED = 'do.already-subscribed';
  const DO_ALREADY_UNSUBSCRIBED = 'do.already-unsubscribed';
  const DO_SUBSCRIBED = 'do.subscribed';
  const DO_UNSUBSCRIBED = 'do.unsubscribed';

  protected function applySubscribe(array $phids, $is_add) {
    $adapter = $this->getAdapter();

    if ($is_add) {
      $kind = '+';
    } else {
      $kind = '-';
    }

    $subscriber_phids = array_fuse($phids);
    if (!$subscriber_phids) {
      $this->logEffect(self::DO_NO_TARGETS);
      return;
    }

    // The "Add Subscribers" rule only adds subscribers who haven't previously
    // unsubscribed from the object explicitly. Filter these subscribers out
    // before continuing.
    if ($is_add) {
      $unsubscribed = $adapter->loadEdgePHIDs(
        PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST);

      foreach ($unsubscribed as $phid) {
        if (isset($subscriber_phids[$phid])) {
          $unsubscribed[$phid] = $phid;
          unset($subscriber_phids[$phid]);
        }
      }

      if ($unsubscribed) {
        $this->logEffect(
          self::DO_PREVIOUSLY_UNSUBSCRIBED,
          array_values($unsubscribed));
      }
    }

    if (!$subscriber_phids) {
      return;
    }

    // Filter out PHIDs which aren't valid subscribers. Lower levels of the
    // stack will fail loudly if we try to add subscribers with invalid PHIDs
    // or unknown PHID types, so drop them here.
    $invalid = array();
    foreach ($subscriber_phids as $phid) {
      $type = phid_get_type($phid);
      switch ($type) {
        case PhabricatorPeopleUserPHIDType::TYPECONST:
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          break;
        default:
          $invalid[$phid] = $phid;
          unset($subscriber_phids[$phid]);
          break;
      }
    }

    if ($invalid) {
      $this->logEffect(self::DO_INVALID, array_values($invalid));
    }

    if (!$subscriber_phids) {
      return;
    }

    $auto = array();
    $object = $adapter->getObject();
    foreach ($subscriber_phids as $phid) {
      if ($object->isAutomaticallySubscribed($phid)) {
        $auto[$phid] = $phid;
        unset($subscriber_phids[$phid]);
      }
    }

    if ($auto) {
      $this->logEffect(self::DO_AUTOSUBSCRIBED, array_values($auto));
    }

    if (!$subscriber_phids) {
      return;
    }

    $current = $adapter->loadEdgePHIDs(
      PhabricatorObjectHasSubscriberEdgeType::EDGECONST);

    if ($is_add) {
      $already = array();
      foreach ($subscriber_phids as $phid) {
        if (isset($current[$phid])) {
          $already[$phid] = $phid;
          unset($subscriber_phids[$phid]);
        }
      }

      if ($already) {
        $this->logEffect(self::DO_ALREADY_SUBSCRIBED, $already);
      }
    } else {
      $already = array();
      foreach ($subscriber_phids as $phid) {
        if (empty($current[$phid])) {
          $already[$phid] = $phid;
          unset($subscriber_phids[$phid]);
        }
      }

      if ($already) {
        $this->logEffect(self::DO_ALREADY_UNSUBSCRIBED, $already);
      }
    }

    if (!$subscriber_phids) {
      return;
    }

    $xaction = $adapter->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
      ->setNewValue(
        array(
          $kind => $subscriber_phids,
        ));

    $adapter->queueTransaction($xaction);

    if ($is_add) {
      $this->logEffect(self::DO_SUBSCRIBED, $subscriber_phids);
    } else {
      $this->logEffect(self::DO_UNSUBSCRIBED, $subscriber_phids);
    }
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_NO_TARGETS => array(
        'icon' => 'fa-ban',
        'color' => 'grey',
        'name' => pht('No Targets'),
      ),
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
      self::DO_INVALID => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('Invalid Targets'),
      ),
      self::DO_ALREADY_SUBSCRIBED => array(
        'icon' => 'fa-chevron-right',
        'color' => 'grey',
        'name' => pht('Already Subscribed'),
      ),
      self::DO_ALREADY_UNSUBSCRIBED => array(
        'icon' => 'fa-chevron-right',
        'color' => 'grey',
        'name' => pht('Already Unsubscribed'),
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

  public function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_NO_TARGETS:
        return pht('Rule lists no targets.');
      case self::DO_PREVIOUSLY_UNSUBSCRIBED:
        return pht(
          'Declined to resubscribe %s target(s) because they previously '.
          'unsubscribed: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_INVALID:
        return pht(
          'Declined to act on %s invalid target(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_AUTOSUBSCRIBED:
        return pht(
          '%s automatically subscribed target(s) were not affected: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ALREADY_SUBSCRIBED:
        return pht(
          '%s target(s) are already subscribed: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ALREADY_UNSUBSCRIBED:
        return pht(
          '%s target(s) are not subscribed: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_SUBSCRIBED:
        return pht(
          'Added %s subscriber(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_UNSUBSCRIBED:
        return pht(
          'Removed %s subscriber(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
    }
  }


}
