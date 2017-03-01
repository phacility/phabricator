<?php

final class PhabricatorPackagesPublisher
  extends PhabricatorPackagesDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSubscribableInterface,
    PhabricatorProjectInterface,
    PhabricatorConduitResultInterface,
    PhabricatorNgramsInterface {

  protected $name;
  protected $publisherKey;
  protected $editPolicy;

  public static function initializeNewPublisher(PhabricatorUser $actor) {
    $packages_application = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPackagesApplication'))
      ->executeOne();

    $edit_policy = $packages_application->getPolicy(
      PhabricatorPackagesPublisherDefaultEditCapability::CAPABILITY);

    return id(new self())
      ->setEditPolicy($edit_policy);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort64',
        'publisherKey' => 'sort64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_publisher' => array(
          'columns' => array('publisherKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPackagesPublisherPHIDType::TYPECONST);
  }

  public function getURI() {
    $publisher_key = $this->getPublisherKey();
    return "/package/{$publisher_key}/";
  }

  public static function assertValidPublisherName($value) {
    $length = phutil_utf8_strlen($value);
    if (!$length) {
      throw new Exception(
        pht(
          'Publisher name "%s" is not valid: publisher names are required.',
          $value));
    }

    $max_length = 64;
    if ($length > $max_length) {
      throw new Exception(
        pht(
          'Publisher name "%s" is not valid: publisher names must not be '.
          'more than %s characters long.',
          $value,
          new PhutilNumber($max_length)));
    }
  }

  public static function assertValidPublisherKey($value) {
    $length = phutil_utf8_strlen($value);
    if (!$length) {
      throw new Exception(
        pht(
          'Publisher key "%s" is not valid: publisher keys are required.',
          $value));
    }

    $max_length = 64;
    if ($length > $max_length) {
      throw new Exception(
        pht(
          'Publisher key "%s" is not valid: publisher keys must not be '.
          'more than %s characters long.',
          $value,
          new PhutilNumber($max_length)));
    }

    if (!preg_match('/^[a-z]+\z/', $value)) {
      throw new Exception(
        pht(
          'Publisher key "%s" is not valid: publisher keys may only contain '.
          'lowercase latin letters.',
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
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $viewer = $engine->getViewer();

    $this->openTransaction();

      $packages = id(new PhabricatorPackagesPackageQuery())
        ->setViewer($viewer)
        ->withPublisherPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($packages as $package) {
        $engine->destroyObject($package);
      }

      $this->delete();

    $this->saveTransaction();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorPackagesPublisherEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorPackagesPublisherTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new PhabricatorPackagesPublisherNameNgrams())
        ->setValue($this->getName()),
    );
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the publisher.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('publisherKey')
        ->setType('string')
        ->setDescription(pht('The unique key of the publisher.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
      'publisherKey' => $this->getPublisherKey(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


}
