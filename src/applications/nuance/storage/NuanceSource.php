<?php

final class NuanceSource extends NuanceDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $type;
  protected $data;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(NuanceSourcePHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getURI() {
    return '/nuance/source/view/'.$this->getID().'/';
  }

  public static function initializeNewSource(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorNuanceApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      NuanceSourceDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(
      NuanceSourceDefaultEditCapability::CAPABILITY);

    $definitions = NuanceSourceDefinition::getAllDefinitions();
    $lucky_definition = head($definitions);

    return id(new NuanceSource())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setType($lucky_definition->getSourceTypeConstant());
  }

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

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
