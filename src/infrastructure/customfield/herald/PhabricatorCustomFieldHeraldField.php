<?php

final class PhabricatorCustomFieldHeraldField extends HeraldField {

  const FIELDCONST = 'herald.custom';

  private $customField;

  public function setCustomField(PhabricatorCustomField $custom_field) {
    $this->customField = $custom_field;
    return $this;
  }

  public function getCustomField() {
    return $this->customField;
  }

  public function getFieldGroupKey() {
    return PhabricatorCustomFieldHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorCustomFieldInterface);
  }

  public function getFieldsForObject($object) {
    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_HERALD);
    $field_list->setViewer(PhabricatorUser::getOmnipotentUser());
    $field_list->readFieldsFromStorage($object);

    $prefix = 'herald.custom/';
    $limit = self::getFieldConstantByteLimit();

    $map = array();
    foreach ($field_list->getFields() as $field) {
      $key = $field->getFieldKey();

      // NOTE: This use of digestToLength() isn't preferred (you should
      // normally digest a key unconditionally, so that it isn't possible to
      // arrange a collision) but preserves backward compatibility.

      $full_key = $prefix.$key;
      if (strlen($full_key) > $limit) {
        $full_key = PhabricatorHash::digestToLength($full_key, $limit);
      }

      $map[$full_key] = id(new PhabricatorCustomFieldHeraldField())
        ->setCustomField($field);
    }

    return $map;
  }

  public function getHeraldFieldName() {
    return $this->getCustomField()->getHeraldFieldName();
  }

  public function getHeraldFieldValue($object) {
    return $this->getCustomField()->getHeraldFieldValue();
  }

  public function getHeraldFieldConditions() {
    return $this->getCustomField()->getHeraldFieldConditions();
  }

  public function getHeraldFieldValueType($condition) {
    return $this->getCustomField()->getHeraldFieldValueType($condition);
  }

}
