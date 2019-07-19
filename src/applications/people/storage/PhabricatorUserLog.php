<?php

final class PhabricatorUserLog extends PhabricatorUserDAO
  implements PhabricatorPolicyInterface {

  protected $actorPHID;
  protected $userPHID;
  protected $action;
  protected $oldValue;
  protected $newValue;
  protected $details = array();
  protected $remoteAddr;
  protected $session;

  public static function initializeNewLog(
    PhabricatorUser $actor = null,
    $object_phid = null,
    $action = null) {

    $log = new PhabricatorUserLog();

    if ($actor) {
      $log->setActorPHID($actor->getPHID());
      if ($actor->hasSession()) {
        $session = $actor->getSession();

        // NOTE: This is a hash of the real session value, so it's safe to
        // store it directly in the logs.
        $log->setSession($session->getSessionKey());
      }
    }

    $log->setUserPHID((string)$object_phid);
    $log->setAction($action);

    $address = PhabricatorEnv::getRemoteAddress();
    if ($address) {
      $log->remoteAddr = $address->getAddress();
    } else {
      $log->remoteAddr = '';
    }

    return $log;
  }

  public static function loadRecentEventsFromThisIP($action, $timespan) {
    $address = PhabricatorEnv::getRemoteAddress();
    if (!$address) {
      return array();
    }

    return id(new PhabricatorUserLog())->loadAllWhere(
      'action = %s AND remoteAddr = %s AND dateCreated > %d
        ORDER BY dateCreated DESC',
      $action,
      $address->getAddress(),
      PhabricatorTime::getNow() - $timespan);
  }

  public function save() {
    $this->details['host'] = php_uname('n');
    $this->details['user_agent'] = AphrontRequest::getHTTPHeader('User-Agent');

    return parent::save();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
        'details'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'actorPHID' => 'phid?',
        'action' => 'text64',
        'remoteAddr' => 'text64',
        'session' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'actorPHID' => array(
          'columns' => array('actorPHID', 'dateCreated'),
        ),
        'userPHID' => array(
          'columns' => array('userPHID', 'dateCreated'),
        ),
        'action' => array(
          'columns' => array('action', 'dateCreated'),
        ),
        'dateCreated' => array(
          'columns' => array('dateCreated'),
        ),
        'remoteAddr' => array(
          'columns' => array('remoteAddr', 'dateCreated'),
        ),
        'session' => array(
          'columns' => array('session', 'dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getURI() {
    return urisprintf('/people/logs/%s/', $this->getID());
  }

  public function getObjectName() {
    return pht('Activity Log %d', $this->getID());
  }

  public function getRemoteAddressForViewer(PhabricatorUser $viewer) {
    $viewer_phid = $viewer->getPHID();
    $actor_phid = $this->getActorPHID();
    $user_phid = $this->getUserPHID();

    if (!$viewer_phid) {
      $can_see_ip = false;
    } else if ($viewer->getIsAdmin()) {
      $can_see_ip = true;
    } else if ($viewer_phid == $actor_phid) {
      // You can see the address if you took the action.
      $can_see_ip = true;
    } else if (!$actor_phid && ($viewer_phid == $user_phid)) {
      // You can see the address if it wasn't authenticated and applied
      // to you (partial login).
      $can_see_ip = true;
    } else {
      // You can't see the address when an administrator disables your
      // account, since it's their address.
      $can_see_ip = false;
    }

    if (!$can_see_ip) {
      return null;
    }

    return $this->getRemoteAddr();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($viewer->getIsAdmin()) {
      return true;
    }

    $viewer_phid = $viewer->getPHID();
    if ($viewer_phid) {
      $user_phid = $this->getUserPHID();
      if ($viewer_phid == $user_phid) {
        return true;
      }

      $actor_phid = $this->getActorPHID();
      if ($viewer_phid == $actor_phid) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return array(
      pht('Users can view their activity and activity that affects them.'),
      pht('Administrators can always view all activity.'),
    );
  }

}
