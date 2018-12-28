<?php

final class DivinerLiveBook extends DivinerDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorFulltextInterface {

  protected $name;
  protected $repositoryPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $configurationData = array();

  private $projectPHIDs = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'configurationData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text64',
        'repositoryPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'name' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getConfig($key, $default = null) {
    return idx($this->configurationData, $key, $default);
  }

  public function setConfig($key, $value) {
    $this->configurationData[$key] = $value;
    return $this;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(DivinerBookPHIDType::TYPECONST);
  }

  public function getTitle() {
    return $this->getConfig('title', $this->getName());
  }

  public function getShortTitle() {
    return $this->getConfig('short', $this->getTitle());
  }

  public function getPreface() {
    return $this->getConfig('preface');
  }

  public function getGroupName($group) {
    $groups = $this->getConfig('groups', array());
    $spec = idx($groups, $group, array());
    return idx($spec, 'name', $group);
  }

  public function attachRepository(PhabricatorRepository $repository = null) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachProjectPHIDs(array $project_phids) {
    $this->projectPHIDs = $project_phids;
    return $this;
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->projectPHIDs);
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
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $atoms = id(new DivinerAtomQuery())
        ->setViewer($engine->getViewer())
        ->withBookPHIDs(array($this->getPHID()))
        ->execute();

      foreach ($atoms as $atom) {
        $engine->destroyObject($atom);
      }

      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DivinerLiveBookEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new DivinerLiveBookTransaction();
  }

/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new DivinerLiveBookFulltextEngine();
  }


}
