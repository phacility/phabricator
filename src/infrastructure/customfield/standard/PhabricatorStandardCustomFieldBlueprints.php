<?php

final class PhabricatorStandardCustomFieldBlueprints
  extends PhabricatorStandardCustomFieldTokenizer {

  public function getFieldType() {
    return 'blueprints';
  }

  public function getDatasource() {
    return new DrydockBlueprintDatasource();
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {

    $old = $this->decodeValue($xaction->getOldValue());
    $new = $this->decodeValue($xaction->getNewValue());

    DrydockAuthorization::applyAuthorizationChanges(
      $this->getViewer(),
      $xaction->getObjectPHID(),
      $old,
      $new);
  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();
    if (!$value) {
      return phutil_tag('em', array(), pht('No authorized blueprints.'));
    }

    return id(new DrydockObjectAuthorizationView())
      ->setUser($this->getViewer())
      ->setObjectPHID($this->getObject()->getPHID())
      ->setBlueprintPHIDs($value);
  }



}
