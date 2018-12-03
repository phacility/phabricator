<?php

final class PhabricatorSubtypeEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'editengine.subtype';
  const EDITKEY = 'subtype';

  public function getExtensionPriority() {
    return 8000;
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Subtypes');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {
    return $engine->supportsSubtypes();
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $subtype_type = PhabricatorTransactions::TYPE_SUBTYPE;

    $map = $object->newEditEngineSubtypeMap();
    $options = mpull($map, 'getName');

    $subtype_field = id(new PhabricatorSelectEditField())
      ->setKey(self::EDITKEY)
      ->setLabel(pht('Subtype'))
      ->setIsFormField(false)
      ->setTransactionType($subtype_type)
      ->setConduitDescription(pht('Change the object subtype.'))
      ->setConduitTypeDescription(pht('New object subtype key.'))
      ->setValue($object->getEditEngineSubtype())
      ->setOptions($options);

    // If subtypes are configured, enable changing them from the bulk editor
    // and comment action stack.
    if (count($map) > 1) {
      $subtype_field
        ->setBulkEditLabel(pht('Change subtype to'))
        ->setCommentActionLabel(pht('Change Subtype'))
        ->setCommentActionOrder(3000);
    }

    return array(
      $subtype_field,
    );
  }

}
