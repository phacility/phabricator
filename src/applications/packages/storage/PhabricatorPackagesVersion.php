<?php

final class PhabricatorPackagesVersion
  extends PhabricatorPackagesDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSubscribableInterface,
    PhabricatorProjectInterface,
    PhabricatorConduitResultInterface,
    PhabricatorNgramsInterface {

  protected $name;
  protected $packagePHID;

  private $package;

  public static function initializeNewVersion(PhabricatorUser $actor) {
    return id(new self());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_package' => array(
          'columns' => array('packagePHID', 'name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPackagesVersionPHIDType::TYPECONST);
  }

  public function getURI() {
    $package = $this->getPackage();
    $full_key = $package->getFullKey();
    $name = $this->getName();

    return "/package/{$full_key}/{$name}/";
  }

  public function attachPackage(PhabricatorPackagesPackage $package) {
    $this->package = $package;
    return $this;
  }

  public function getPackage() {
    return $this->assertAttached($this->package);
  }

  public static function assertValidVersionName($value) {
    $length = phutil_utf8_strlen($value);
    if (!$length) {
      throw new Exception(
        pht(
          'Version name "%s" is not valid: version names are required.',
          $value));
    }

    $max_length = 64;
    if ($length > $max_length) {
      throw new Exception(
        pht(
          'Version name "%s" is not valid: version names must not be '.
          'more than %s characters long.',
          $value,
          new PhutilNumber($max_length)));
    }

    if (!preg_match('/^[A-Za-z0-9.-]+\z/', $value)) {
      throw new Exception(
        pht(
          'Version name "%s" is not valid: version names may only contain '.
          'latin letters, digits, periods, and hyphens.',
          $value));
    }

    if (preg_match('/^[.-]|[.-]$/', $value)) {
      throw new Exception(
        pht(
          'Version name "%s" is not valid: version names may not start or '.
          'end with a period or hyphen.',
          $value));
    }
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }


/* -(  Policy Interface  )--------------------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array(
        $this->getPackage(),
        $capability,
      ),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorPackagesVersionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorPackagesVersionTransaction();
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new PhabricatorPackagesVersionNameNgrams())
        ->setValue($this->getName()),
    );
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the version.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


}
