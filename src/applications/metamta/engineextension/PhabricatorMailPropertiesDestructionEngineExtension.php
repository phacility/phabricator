<?php

final class PhabricatorMailPropertiesDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'mail.properties';

  public function getExtensionName() {
    return pht('Mail Properties');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $object_phid = $object->getPHID();
    $viewer = $engine->getViewer();

    $properties = id(new PhabricatorMetaMTAMailPropertiesQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object_phid))
      ->executeOne();
    if ($properties) {
      $properties->delete();
    }
  }

}
