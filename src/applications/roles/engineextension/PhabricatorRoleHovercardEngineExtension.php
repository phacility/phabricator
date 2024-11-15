<?php

final class PhabricatorRoleHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'role.card';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Role Card');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof PhabricatorRole);
  }

  public function willRenderHovercards(array $objects) {
    $viewer = $this->getViewer();
    $phids = mpull($objects, 'getPHID');

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->needImages(true)
      ->execute();
    $roles = mpull($roles, null, 'getPHID');

    return array(
      'roles' => $roles,
    );
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $object,
    $data) {
    $viewer = $this->getViewer();

    $role = idx($data['roles'], $object->getPHID());
    if (!$role) {
      return;
    }

    $role_card = id(new PhabricatorRoleCardView())
      ->setRole($role)
      ->setViewer($viewer);

    $hovercard->appendChild($role_card);
  }

}
