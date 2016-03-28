<?php

final class DoorkeeperBridgedObjectCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'doorkeeper.bridged-object';

  public function shouldEnableForObject($object) {
    return ($object instanceof DoorkeeperBridgedObjectInterface);
  }

  public function getExtensionApplication() {
    return new PhabricatorDoorkeeperApplication();
  }

  public function buildCurtainPanel($object) {
    $xobj = $object->getBridgedObject();
    if (!$xobj) {
      return null;
    }

    $tag = id(new DoorkeeperTagView())
      ->setExternalObject($xobj);

    return $this->newPanel()
      ->setHeaderText(pht('Imported From'))
      ->setOrder(5000)
      ->appendChild($tag);
  }

}
