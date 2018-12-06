<?php


final class PhabricatorEditEngineSubtypeMap
  extends Phobject {

  private $subtypes;

  public function __construct(array $subtypes) {
    assert_instances_of($subtypes, 'PhabricatorEditEngineSubtype');

    $this->subtypes = $subtypes;
  }

  public function getDisplayMap() {
    return mpull($this->subtypes, 'getName');
  }

  public function getCount() {
    return count($this->subtypes);
  }

  public function isValidSubtype($subtype_key) {
    return isset($this->subtypes[$subtype_key]);
  }

  public function getSubtypes() {
    return $this->subtypes;
  }

  public function getSubtype($subtype_key) {
    if (!$this->isValidSubtype($subtype_key)) {
      throw new Exception(
        pht(
          'Subtype key "%s" does not identify a valid subtype.',
          $subtype_key));
    }

    return $this->subtypes[$subtype_key];
  }

}
