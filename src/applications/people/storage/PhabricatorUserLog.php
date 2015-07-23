<?php

final class PhabricatorUserLog extends PhabricatorUserDAO
  implements PhabricatorPolicyInterface {

  const ACTION_LOGIN          = 'login';
  const ACTION_LOGIN_PARTIAL  = 'login-partial';
  const ACTION_LOGIN_FULL     = 'login-full';
  const ACTION_LOGOUT         = 'logout';
  const ACTION_LOGIN_FAILURE  = 'login-fail';
  const ACTION_LOGIN_LEGALPAD = 'login-legalpad';
  const ACTION_RESET_PASSWORD = 'reset-pass';

  const ACTION_CREATE         = 'create';
  const ACTION_EDIT           = 'edit';

  const ACTION_ADMIN          = 'admin';
  const ACTION_SYSTEM_AGENT   = 'system-agent';
  const ACTION_MAILING_LIST   = 'mailing-list';
  const ACTION_DISABLE        = 'disable';
  const ACTION_APPROVE        = 'approve';
  const ACTION_DELETE         = 'delete';

  const ACTION_CONDUIT_CERTIFICATE = 'conduit-cert';
  const ACTION_CONDUIT_CERTIFICATE_FAILURE = 'conduit-cert-fail';

  const ACTION_EMAIL_PRIMARY    = 'email-primary';
  const ACTION_EMAIL_REMOVE     = 'email-remove';
  const ACTION_EMAIL_ADD        = 'email-add';
  const ACTION_EMAIL_VERIFY     = 'email-verify';
  const ACTION_EMAIL_REASSIGN   = 'email-reassign';

  const ACTION_CHANGE_PASSWORD  = 'change-password';
  const ACTION_CHANGE_USERNAME  = 'change-username';

  const ACTION_ENTER_HISEC = 'hisec-enter';
  const ACTION_EXIT_HISEC = 'hisec-exit';
  const ACTION_FAIL_HISEC = 'hisec-fail';

  const ACTION_MULTI_ADD = 'multi-add';
  const ACTION_MULTI_REMOVE = 'multi-remove';

  protected $actorPHID;
  protected $userPHID;
  protected $action;
  protected $oldValue;
  protected $newValue;
  protected $details = array();
  protected $remoteAddr;
  protected $session;

  public static function getActionTypeMap() {
    return array(
      self::ACTION_LOGIN => pht('Login'),
      self::ACTION_LOGIN_PARTIAL => pht('Login: Partial Login'),
      self::ACTION_LOGIN_FULL => pht('Login: Upgrade to Full'),
      self::ACTION_LOGIN_FAILURE => pht('Login: Failure'),
      self::ACTION_LOGIN_LEGALPAD =>
        pht('Login: Signed Required Legalpad Documents'),
      self::ACTION_LOGOUT => pht('Logout'),
      self::ACTION_RESET_PASSWORD => pht('Reset Password'),
      self::ACTION_CREATE => pht('Create Account'),
      self::ACTION_EDIT => pht('Edit Account'),
      self::ACTION_ADMIN => pht('Add/Remove Administrator'),
      self::ACTION_SYSTEM_AGENT => pht('Add/Remove System Agent'),
      self::ACTION_MAILING_LIST => pht('Add/Remove Mailing List'),
      self::ACTION_DISABLE => pht('Enable/Disable'),
      self::ACTION_APPROVE => pht('Approve Registration'),
      self::ACTION_DELETE => pht('Delete User'),
      self::ACTION_CONDUIT_CERTIFICATE
        => pht('Conduit: Read Certificate'),
      self::ACTION_CONDUIT_CERTIFICATE_FAILURE
        => pht('Conduit: Read Certificate Failure'),
      self::ACTION_EMAIL_PRIMARY => pht('Email: Change Primary'),
      self::ACTION_EMAIL_ADD => pht('Email: Add Address'),
      self::ACTION_EMAIL_REMOVE => pht('Email: Remove Address'),
      self::ACTION_EMAIL_VERIFY => pht('Email: Verify'),
      self::ACTION_EMAIL_REASSIGN => pht('Email: Reassign'),
      self::ACTION_CHANGE_PASSWORD => pht('Change Password'),
      self::ACTION_CHANGE_USERNAME => pht('Change Username'),
      self::ACTION_ENTER_HISEC => pht('Hisec: Enter'),
      self::ACTION_EXIT_HISEC => pht('Hisec: Exit'),
      self::ACTION_FAIL_HISEC => pht('Hisec: Failed Attempt'),
      self::ACTION_MULTI_ADD => pht('Multi-Factor: Add Factor'),
      self::ACTION_MULTI_REMOVE => pht('Multi-Factor: Remove Factor'),
    );
  }


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

    $log->remoteAddr = idx($_SERVER, 'REMOTE_ADDR', '');

    return $log;
  }

  public static function loadRecentEventsFromThisIP($action, $timespan) {
    return id(new PhabricatorUserLog())->loadAllWhere(
      'action = %s AND remoteAddr = %s AND dateCreated > %d
        ORDER BY dateCreated DESC',
      $action,
      idx($_SERVER, 'REMOTE_ADDR'),
      time() - $timespan);
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
        'session' => 'bytes40?',
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
