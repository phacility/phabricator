<?php

final class PhabricatorFlagDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'flags';

  public function getExtensionName() {
    return pht('Flags');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $object_phid = $object->getPHID();

    if ($object instanceof PhabricatorFlaggableInterface) {
      $flags = id(new PhabricatorFlag())->loadAllWhere(
        'objectPHID = %s',
        $object_phid);
      foreach ($flags as $flag) {
        $flag->delete();
      }
    }

    $flags = id(new PhabricatorFlag())->loadAllWhere(
      'ownerPHID = %s',
      $object_phid);
    foreach ($flags as $flag) {
      $flag->delete();
    }
  }

}
