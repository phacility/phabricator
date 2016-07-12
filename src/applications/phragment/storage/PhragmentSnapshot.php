<?php

final class PhragmentSnapshot extends PhragmentDAO
  implements PhabricatorPolicyInterface {

  protected $primaryFragmentPHID;
  protected $name;

  private $primaryFragment = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
          'columns' => array('primaryFragmentPHID', 'name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhragmentSnapshotPHIDType::TYPECONST);
  }

  public function getURI() {
    return '/phragment/snapshot/view/'.$this->getID().'/';
  }

  public function getPrimaryFragment() {
    return $this->assertAttached($this->primaryFragment);
  }

  public function attachPrimaryFragment(PhragmentFragment $fragment) {
    return $this->primaryFragment = $fragment;
  }

  public function delete() {
    $children = id(new PhragmentSnapshotChild())
      ->loadAllWhere('snapshotPHID = %s', $this->getPHID());
    $this->openTransaction();
      foreach ($children as $child) {
        $child->delete();
      }
      $result = parent::delete();
    $this->saveTransaction();
    return $result;
  }


/* -(  Policy Interface  )--------------------------------------------------- */


  public function getCapabilities() {
    return $this->getPrimaryFragment()->getCapabilities();
  }

  public function getPolicy($capability) {
    return $this->getPrimaryFragment()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getPrimaryFragment()
      ->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return $this->getPrimaryFragment()
      ->describeAutomaticCapability($capability);
  }
}
