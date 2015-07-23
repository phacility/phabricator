<?php

final class ReleephBranch extends ReleephDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $releephProjectID;
  protected $isActive;
  protected $createdByUserPHID;

  // The immutable name of this branch ('releases/foo-2013.01.24')
  protected $name;
  protected $basename;

  // The symbolic name of this branch (LATEST, PRODUCTION, RC, ...)
  // See SYMBOLIC_NAME_NOTE below
  protected $symbolicName;

  // Where to cut the branch
  protected $cutPointCommitPHID;

  protected $details = array();

  private $project = self::ATTACHABLE;
  private $cutPointCommit = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'basename' => 'text64',
        'isActive' => 'bool',
        'symbolicName' => 'text64?',
        'name' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'releephProjectID' => array(
          'columns' => array('releephProjectID', 'symbolicName'),
          'unique' => true,
        ),
        'releephProjectID_2' => array(
          'columns' => array('releephProjectID', 'basename'),
          'unique' => true,
        ),
        'releephProjectID_name' => array(
          'columns' => array('releephProjectID', 'name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(ReleephBranchPHIDType::TYPECONST);
  }

  public function getDetail($key, $default = null) {
    return idx($this->getDetails(), $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  protected function willWriteData(array &$data) {
    // If symbolicName is omitted, set it to the basename.
    //
    // This means that we can enforce symbolicName as a UNIQUE column in the
    // DB. We'll interpret symbolicName === basename as meaning "no symbolic
    // name".
    //
    // SYMBOLIC_NAME_NOTE
    if (!$data['symbolicName']) {
      $data['symbolicName'] = $data['basename'];
    }
    parent::willWriteData($data);
  }

  public function getSymbolicName() {
    // See SYMBOLIC_NAME_NOTE above for why this is needed
    if ($this->symbolicName == $this->getBasename()) {
      return '';
    }
    return $this->symbolicName;
  }

  public function setSymbolicName($name) {
    if ($name) {
      parent::setSymbolicName($name);
    } else {
      parent::setSymbolicName($this->getBasename());
    }
    return $this;
  }

  public function getDisplayName() {
    if ($sn = $this->getSymbolicName()) {
      return $sn;
    }
    return $this->getBasename();
  }

  public function getDisplayNameWithDetail() {
    $n = $this->getBasename();
    if ($sn = $this->getSymbolicName()) {
      return "{$sn} ({$n})";
    } else {
      return $n;
    }
  }

  public function getURI($path = null) {
    $components = array(
      '/releeph/branch',
      $this->getID(),
      $path,
    );
    return implode('/', $components);
  }

  public function isActive() {
    return $this->getIsActive();
  }

  public function attachProject(ReleephProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->assertAttached($this->project);
  }

  public function getProduct() {
    return $this->getProject();
  }

  public function attachCutPointCommit(
    PhabricatorRepositoryCommit $commit = null) {
    $this->cutPointCommit = $commit;
    return $this;
  }

  public function getCutPointCommit() {
    return $this->assertAttached($this->cutPointCommit);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new ReleephBranchEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new ReleephBranchTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return $this->getProduct()->getCapabilities();
  }

  public function getPolicy($capability) {
    return $this->getProduct()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getProduct()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Release branches have the same policies as the product they are a '.
      'part of.');
  }


}
