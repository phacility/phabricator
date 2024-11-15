<?php

final class PhabricatorRolesSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Roles');
  }

  public function getAttachmentDescription() {
    return pht('Get information about roles.');
  }

  public function loadAttachmentData(array $objects, $spec) {
    $object_phids = mpull($objects, 'getPHID');

    $roles_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($object_phids)
      ->withEdgeTypes(
        array(
          PhabricatorRoleObjectHasRoleEdgeType::EDGECONST,
        ));
    $roles_query->execute();

    return array(
      'roles.query' => $roles_query,
    );
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $roles_query = $data['roles.query'];
    $object_phid = $object->getPHID();

    $role_phids = $roles_query->getDestinationPHIDs(
      array($object_phid),
      array(PhabricatorRoleObjectHasRoleEdgeType::EDGECONST));

    return array(
      'rolePHIDs' => array_values($role_phids),
    );
  }

}
