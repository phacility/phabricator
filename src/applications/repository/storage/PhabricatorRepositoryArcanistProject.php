<?php

/**
 * @group repository
 */
final class PhabricatorRepositoryArcanistProject
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $phid;
  protected $repositoryID;

  protected $symbolIndexLanguages = array();
  protected $symbolIndexProjects  = array();

  private $repository = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'symbolIndexLanguages' => self::SERIALIZATION_JSON,
        'symbolIndexProjects'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPHIDTypeArcanistProject::TYPECONST);
  }

  // TODO: Remove.
  public function loadRepository() {
    if (!$this->getRepositoryID()) {
      return null;
    }
    return id(new PhabricatorRepository())->load($this->getRepositoryID());
  }

  public function delete() {
    $this->openTransaction();

      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE arcanistProjectID = %d',
        id(new PhabricatorRepositorySymbol())->getTableName(),
        $this->getID());

      $result = parent::delete();
    $this->saveTransaction();
    return $result;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachRepository(PhabricatorRepository $repository = null) {
    $this->repository = $repository;
    return $this;
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
        return PhabricatorPolicies::POLICY_USER;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_ADMIN;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
