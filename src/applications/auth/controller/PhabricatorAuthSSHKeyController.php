<?php

abstract class PhabricatorAuthSSHKeyController
  extends PhabricatorAuthController {

  protected function newKeyForObjectPHID($object_phid) {
    $viewer = $this->getViewer();

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$object) {
      return null;
    }

    // If this kind of object can't have SSH keys, don't let the viewer
    // add them.
    if (!($object instanceof PhabricatorSSHPublicKeyInterface)) {
      return null;
    }

    return id(new PhabricatorAuthSSHKey())
      ->setObjectPHID($object_phid)
      ->attachObject($object);
  }

}
