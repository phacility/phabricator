<?php

final class PhabricatorRolesMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'roles';

  public function supportsObject($object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  public function newMailStampTemplates($object) {
    return array(
      id(new PhabricatorPHIDMailStamp())
        ->setKey('tag')
        ->setLabel(pht('Tagged with Role')),
    );
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    $role_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);

    $this->getMailStamp('tag')
      ->setValue($role_phids);
  }

}
