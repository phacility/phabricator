<?php

final class PhabricatorProfileMenuItemIndexEngineExtension
  extends PhabricatorEdgeIndexEngineExtension {

  const EXTENSIONKEY = 'profile.menu.item';

  public function getExtensionName() {
    return pht('Profile Menu Item');
  }

  public function shouldIndexObject($object) {
    if (!($object instanceof PhabricatorProfileMenuItemConfiguration)) {
      return false;
    }

    return true;
  }

  protected function getIndexEdgeType() {
    return PhabricatorProfileMenuItemAffectsObjectEdgeType::EDGECONST;
  }

  protected function getIndexDestinationPHIDs($object) {
    return $object->getAffectedObjectPHIDs();
  }

}
