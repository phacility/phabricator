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

    $subtype_field = id(new PhabricatorSelectEditField())
      ->setKey(self::EDITKEY)
      ->setLabel(pht('Subtype'))
      ->setIsConduitOnly(true)
      ->setIsHidden(true)
      ->setIsReorderable(false)
      ->setIsDefaultable(false)
      ->setIsLockable(false)
      ->setTransactionType($subtype_type)
      ->setConduitDescription(pht('Change the object subtype.'))
      ->setConduitTypeDescription(pht('New object subtype key.'))
      ->setValue($object->getEditEngineSubtype());

    return array(
      $subtype_field,
    );
  }

}
