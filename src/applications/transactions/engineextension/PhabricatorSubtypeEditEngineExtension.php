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
    return ($object instanceof PhabricatorEditEngineSubtypeInterface);
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $subtype_type = PhabricatorTransactions::TYPE_SUBTYPE;
    $subtype_value = $object->getEditEngineSubtype();

    $map = $object->newEditEngineSubtypeMap();

    if ($object->getID()) {
      $options = $map->getMutationMap($subtype_value);
    } else {
      // NOTE: This is a crude proxy for "are we in the bulk edit workflow".
      // We want to allow any mutation.
      $options = $map->getDisplayMap();
    }

    $subtype_field = id(new PhabricatorSelectEditField())
      ->setKey(self::EDITKEY)
      ->setLabel(pht('Subtype'))
      ->setIsFormField(false)
      ->setTransactionType($subtype_type)
      ->setConduitDescription(pht('Change the object subtype.'))
      ->setConduitTypeDescription(pht('New object subtype key.'))
      ->setValue($subtype_value)
      ->setOptions($options);

    // If subtypes are configured, enable changing them from the bulk editor.
    // Bulk editor
    if ($options) {
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
