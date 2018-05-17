<?php

final class PhabricatorCustomFieldEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'customfield.fields';

  public function getExtensionPriority() {
    return 5000;
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Custom Fields');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorCustomFieldInterface);
  }

  public function newBulkEditGroups(PhabricatorEditEngine $engine) {
    return array(
      id(new PhabricatorBulkEditGroup())
        ->setKey('custom')
        ->setLabel(pht('Custom Fields')),
    );
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $viewer = $this->getViewer();

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_EDITENGINE);

    $field_list->setViewer($viewer);

    if ($object->getID()) {
      $field_list->readFieldsFromStorage($object);
    }

    $results = array();
    foreach ($field_list->getFields() as $field) {
      $edit_fields = $field->getEditEngineFields($engine);
      foreach ($edit_fields as $edit_field) {
        $group_key = $edit_field->getBulkEditGroupKey();
        if ($group_key === null) {
          $edit_field->setBulkEditGroupKey('custom');
        }

        $results[] = $edit_field;
      }
    }

    return $results;
  }

}
