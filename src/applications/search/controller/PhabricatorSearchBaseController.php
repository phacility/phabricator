<?php

abstract class PhabricatorSearchBaseController extends PhabricatorController {

  protected function loadRelationshipObject() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $phid = $request->getURIData('sourcePHID');

    return id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
  }

  protected function loadRelationship($object) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $relationship_key = $request->getURIData('relationshipKey');

    $list = PhabricatorObjectRelationshipList::newForObject(
      $viewer,
      $object);

    return $list->getRelationship($relationship_key);
  }

}
