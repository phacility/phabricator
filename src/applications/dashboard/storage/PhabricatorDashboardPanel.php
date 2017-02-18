<?php

/**
 * An individual dashboard panel.
 */
final class PhabricatorDashboardPanel
  extends PhabricatorDashboardDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorFlaggableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface {

  protected $name;
  protected $panelType;
  protected $viewPolicy;
  protected $editPolicy;
  protected $authorPHID;
  protected $isArchived = 0;
  protected $properties = array();

  private $customFields = self::ATTACHABLE;

  public static function initializeNewPanel(PhabricatorUser $actor) {
    return id(new PhabricatorDashboardPanel())
      ->setName('')
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy($actor->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort255',
        'panelType' => 'text64',
        'authorPHID' => 'phid',
        'isArchived' => 'bool',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorDashboardPanelPHIDType::TYPECONST);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getMonogram() {
    return 'W'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  public function getPanelTypes() {
    $panel_types = PhabricatorDashboardPanelType::getAllPanelTypes();
    $panel_types = mpull($panel_types, 'getPanelTypeName', 'getPanelTypeKey');
    asort($panel_types);
    $panel_types = (array('' => pht('(All Types)')) + $panel_types);
    return $panel_types;
  }

  public function getStatuses() {
    $statuses =
      array(
        '' => pht('(All Panels)'),
        'active' => pht('Active Panels'),
        'archived' => pht('Archived Panels'),
      );
    return $statuses;
  }

  public function getImplementation() {
    return idx(
      PhabricatorDashboardPanelType::getAllPanelTypes(),
      $this->getPanelType());
  }

  public function requireImplementation() {
    $impl = $this->getImplementation();
    if (!$impl) {
      throw new Exception(
        pht(
          'Attempting to use a panel in a way that requires an '.
          'implementation, but the panel implementation ("%s") is unknown to '.
          'Phabricator.',
          $this->getPanelType()));
    }
    return $impl;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorDashboardPanelTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorDashboardPanelTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
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


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return array();
  }

  public function getCustomFieldBaseClass() {
    return 'PhabricatorDashboardPanelCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorNgramInterface  )------------------------------------------ */


  public function newNgrams() {
    return array(
      id(new PhabricatorDashboardPanelNgrams())
        ->setValue($this->getName()),
    );
  }

}
