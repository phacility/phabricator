<?php

final class PhabricatorSpacesMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'spaces';

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSpacesInterface);
  }

  public function newMailStampTemplates($object) {
    return array(
      id(new PhabricatorPHIDMailStamp())
        ->setKey('space')
        ->setLabel(pht('Space')),
    );
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    if (!PhabricatorSpacesNamespaceQuery::getSpacesExist()) {
      return;
    }

    $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
      $object);

    $this->getMailStamp('space')
      ->setValue($space_phid);
  }

}
