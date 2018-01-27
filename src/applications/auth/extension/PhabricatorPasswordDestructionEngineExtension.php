<?php

final class PhabricatorPasswordDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'passwords';

  public function getExtensionName() {
    return pht('Passwords');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $viewer = $engine->getViewer();
    $object_phid = $object->getPHID();

    $passwords = id(new PhabricatorAuthPasswordQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object_phid))
      ->execute();

    foreach ($passwords as $password) {
      $engine->destroyObject($password);
    }
  }

}
