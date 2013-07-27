<?php

final class PhabricatorMacroPHIDTypeMacro extends PhabricatorPHIDType {

  const TYPECONST = 'MCRO';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Image Macro');
  }

  public function newObject() {
    return new PhabricatorFileImageMacro();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorMacroQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $macro = $objects[$phid];

      $id = $macro->getID();
      $name = $macro->getName();

      $handle->setName($name);
      $handle->setFullName(pht('Image Macro "%s"', $name));
      $handle->setURI("/macro/view/{$id}/");
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
