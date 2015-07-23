<?php

final class NuanceRequestor
  extends NuanceDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  protected $data = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      NuanceRequestorPHIDType::TYPECONST);
  }

  public static function initializeNewRequestor() {
    return new NuanceRequestor();
  }

  public function getURI() {
    return '/nuance/requestor/view/'.$this->getID().'/';
  }

  public function getPhabricatorUserPHID() {
    return idx($this->getData(), 'phabricatorUserPHID');
  }

  public function getActingAsPHID() {
    $user_phid = $this->getPhabricatorUserPHID();
    if ($user_phid) {
      return $user_phid;
    }

    return id(new PhabricatorNuanceApplication())->getPHID();
  }

  public static function newFromPhabricatorUser(
    PhabricatorUser $viewer,
    PhabricatorContentSource $content_source) {

    // TODO: This is real sketchy and creates a new requestor every time. It
    // shouldn't do that.

    $requestor = self::initializeNewRequestor();

    $xactions = array();

    $properties = array(
      'phabricatorUserPHID' => $viewer->getPHID(),
    );

    foreach ($properties as $key => $value) {
      $xactions[] = id(new NuanceRequestorTransaction())
        ->setTransactionType(NuanceRequestorTransaction::TYPE_PROPERTY)
        ->setMetadataValue(NuanceRequestorTransaction::PROPERTY_KEY, $key)
        ->setNewValue($value);
    }

    $editor = id(new NuanceRequestorEditor())
      ->setActor($viewer)
      ->setContentSource($content_source);

    $editor->applyTransactions($requestor, $xactions);

    return $requestor;
  }

  public function getNuanceProperty($key, $default = null) {
    return idx($this->data, $key, $default);
  }

  public function setNuanceProperty($key, $value) {
    $this->data[$key] = $value;
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
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new NuanceRequestorEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new NuanceRequestorTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

}
