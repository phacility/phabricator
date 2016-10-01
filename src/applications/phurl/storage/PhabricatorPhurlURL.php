<?php

final class PhabricatorPhurlURL extends PhabricatorPhurlDAO
  implements PhabricatorPolicyInterface,
  PhabricatorProjectInterface,
  PhabricatorApplicationTransactionInterface,
  PhabricatorSubscribableInterface,
  PhabricatorTokenReceiverInterface,
  PhabricatorDestructibleInterface,
  PhabricatorMentionableInterface,
  PhabricatorFlaggableInterface,
  PhabricatorSpacesInterface,
  PhabricatorConduitResultInterface,
  PhabricatorNgramsInterface {

  protected $name;
  protected $alias;
  protected $longURL;
  protected $description;

  protected $viewPolicy;
  protected $editPolicy;

  protected $authorPHID;
  protected $spacePHID;

  protected $mailKey;

  const DEFAULT_ICON = 'fa-compress';

  public static function initializeNewPhurlURL(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPhurlApplication'))
      ->executeOne();

    return id(new PhabricatorPhurlURL())
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy($actor->getPHID())
      ->setSpacePHID($actor->getDefaultSpacePHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'alias' => 'sort64?',
        'longURL' => 'text',
        'description' => 'text',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_instance' => array(
          'columns' => array('alias'),
          'unique' => true,
        ),
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPhurlURLPHIDType::TYPECONST);
  }

  public function getMonogram() {
    return 'U'.$this->getID();
  }

  public function getURI() {
    $uri = '/'.$this->getMonogram();
    return $uri;
  }

  public function isValid() {
    $allowed_protocols = PhabricatorEnv::getEnvConfig('uri.allowed-protocols');
    $uri = new PhutilURI($this->getLongURL());

    return isset($allowed_protocols[$uri->getProtocol()]);
  }

  public function getDisplayName() {
    if ($this->getName()) {
      return $this->getName();
    } else {
      return $this->getLongURL();
    }
  }

  public function getRedirectURI() {
    if (strlen($this->getAlias())) {
      $path = '/u/'.$this->getAlias();
    } else {
      $path = '/u/'.$this->getID();
    }
    $domain = PhabricatorEnv::getEnvConfig('phurl.short-uri');
    if (!$domain) {
      $domain = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    }

    $uri = new PhutilURI($domain);
    $uri->setPath($path);
    return (string)$uri;
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $user_phid = $this->getAuthorPHID();
    if ($user_phid) {
      $viewer_phid = $viewer->getPHID();
      if ($viewer_phid == $user_phid) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht('The owner of a URL can always view and edit it.');
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorPhurlURLEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorPhurlURLTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getAuthorPHID());
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }

/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('URL name.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('alias')
        ->setType('string')
        ->setDescription(pht('The alias for the URL.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('longurl')
        ->setType('string')
        ->setDescription(pht('The pre-shortened URL.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('description')
        ->setType('string')
        ->setDescription(pht('A description of the URL.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
      'alias' => $this->getAlias(),
      'description' => $this->getDescription(),
      'urls' => array(
        'long' => $this->getLongURL(),
        'short' => $this->getRedirectURI(),
      ),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

/* -(  PhabricatorNgramInterface  )------------------------------------------ */


  public function newNgrams() {
    return array(
      id(new PhabricatorPhurlURLNameNgrams())
        ->setValue($this->getName()),
    );
  }

}
