<?php

final class PhabricatorRepositoryPullEvent
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $repositoryPHID;
  protected $epoch;
  protected $pullerPHID;
  protected $remoteAddress;
  protected $remoteProtocol;
  protected $resultType;
  protected $resultCode;
  protected $properties;

  private $repository = self::ATTACHABLE;

  const RESULT_PULL = 'pull';
  const RESULT_ERROR = 'error';
  const RESULT_EXCEPTION = 'exception';

  const PROTOCOL_HTTP = 'http';
  const PROTOCOL_HTTPS = 'https';
  const PROTOCOL_SSH = 'ssh';

  public static function initializeNewEvent(PhabricatorUser $viewer) {
    return id(new PhabricatorRepositoryPushEvent())
      ->setPusherPHID($viewer->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'repositoryPHID' => 'phid?',
        'pullerPHID' => 'phid?',
        'remoteAddress' => 'ipaddress?',
        'remoteProtocol' => 'text32?',
        'resultType' => 'text32',
        'resultCode' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_repository' => array(
          'columns' => array('repositoryPHID'),
        ),
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPullEventPHIDType::TYPECONST);
  }

  public function attachRepository(PhabricatorRepository $repository = null) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function getRemoteProtocolDisplayName() {
    $map = array(
      self::PROTOCOL_SSH => pht('SSH'),
      self::PROTOCOL_HTTP => pht('HTTP'),
      self::PROTOCOL_HTTPS => pht('HTTPS'),
    );

    $protocol = $this->getRemoteProtocol();

    return idx($map, $protocol, $protocol);
  }

  public function newResultIcon() {
    $icon = new PHUIIconView();
    $type = $this->getResultType();
    $code = $this->getResultCode();

    $protocol = $this->getRemoteProtocol();

    $is_any_http =
      ($protocol === self::PROTOCOL_HTTP) ||
      ($protocol === self::PROTOCOL_HTTPS);

    // If this was an HTTP request and we responded with a 401, that means
    // the user didn't provide credentials. This is technically an error, but
    // it's routine and just causes the client to prompt them. Show a more
    // comforting icon and description in the UI.
    if ($is_any_http) {
      if ($code == 401) {
        return $icon
          ->setIcon('fa-key blue')
          ->setTooltip(pht('Authentication Required'));
      }
    }

    switch ($type) {
      case self::RESULT_ERROR:
        $icon
          ->setIcon('fa-times red')
          ->setTooltip(pht('Error'));
        break;
      case self::RESULT_EXCEPTION:
        $icon
          ->setIcon('fa-exclamation-triangle red')
          ->setTooltip(pht('Exception'));
        break;
      case self::RESULT_PULL:
        $icon
          ->setIcon('fa-download green')
          ->setTooltip(pht('Pull'));
        break;
      default:
        $icon
          ->setIcon('fa-question indigo')
          ->setTooltip(pht('Unknown ("%s")', $type));
        break;
    }

    return $icon;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    if ($this->getRepository()) {
      return $this->getRepository()->getPolicy($capability);
    }

    return PhabricatorPolicies::POLICY_ADMIN;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if (!$this->getRepository()) {
      return false;
    }

    return $this->getRepository()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      "A repository's pull events are visible to users who can see the ".
      "repository.");
  }

}
