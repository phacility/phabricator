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

  public function getCreateFormsForSubtype(
    PhabricatorEditEngine $edit_engine,
    PhabricatorEditEngineSubtypeInterface $object) {

    $subtype_key = $object->getEditEngineSubtype();
    $subtype = $this->getSubtype($subtype_key);

    // TODO: Allow subtype configuration to specify that children should be
    // created from particular forms or subtypes.
    $select_ids = array();
    $select_subtypes = array();

    $query = $edit_engine->newConfigurationQuery()
      ->withIsDisabled(false);

    if ($select_ids) {
      $query->withIDs($select_ids);
    } else {
      // If we're selecting by subtype rather than selecting specific forms,
      // only select create forms.
      $query->withIsDefault(true);

      if ($select_subtypes) {
        $query->withSubtypes($select_subtypes);
      } else {
        $query->withSubtypes(array($subtype_key));
      }
    }

    $forms = $query->execute();
    $forms = mpull($forms, null, 'getIdentifier');

    // If we're selecting by ID, respect the order specified in the
    // constraint. Otherwise, use the create form sort order.
    if ($select_ids) {
      $forms = array_select_keys($forms, $select_ids) + $forms;
    } else {
      $forms = msort($forms, 'getCreateSortKey');
    }

    return $forms;
  }

}
