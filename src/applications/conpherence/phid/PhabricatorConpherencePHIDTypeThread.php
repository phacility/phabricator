<?php

/**
 * @group conpherence
 */
final class PhabricatorConpherencePHIDTypeThread extends PhabricatorPHIDType {

  const TYPECONST = 'CONP';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Conpherence Thread');
  }

  public function newObject() {
    return new ConpherenceThread();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ConpherenceThreadQuery())
      ->setViewer($query->getViewer())
      ->setParentQuery($query)
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $thread = $objects[$phid];
      $name = $thread->getTitle();
      if (!strlen($name)) {
        $name = pht('[No Title]');
      }
      $handle->setName($name);
      $handle->setFullName($name);
      $handle->setURI('/conpherence/'.$thread->getID().'/');
    }
  }

}
