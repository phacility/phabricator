<?php

final class PhabricatorRolesEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'roles.roles';

  const EDITKEY_ADD = 'roles.add';
  const EDITKEY_SET = 'roles.set';
  const EDITKEY_REMOVE = 'roles.remove';

  public function getExtensionPriority() {
    return 500;
  }

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorRoleApplication');
  }

  public function getExtensionName() {
    return pht('Roles');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    return ($object instanceof PhabricatorRoleInterface);
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $edge_type = PhabricatorTransactions::TYPE_EDGE;
    $role_edge_type = PhabricatorRoleObjectHasRoleEdgeType::EDGECONST;

    $object_phid = $object->getPHID();
    if ($object_phid) {
      $role_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object_phid,
        $role_edge_type);
      $role_phids = array_reverse($role_phids);
    } else {
      $role_phids = array();
    }

    $viewer = $engine->getViewer();

    $roles_field = id(new PhabricatorRolesEditField())
      ->setKey('rolePHIDs')
      ->setLabel(pht('Tags'))
      ->setEditTypeKey('roles')
      ->setAliases(array('role', 'roles', 'tag', 'tags'))
      ->setIsCopyable(true)
      ->setUseEdgeTransactions(true)
      ->setCommentActionLabel(pht('Change Role Tags'))
      ->setCommentActionOrder(8000)
      ->setDescription(pht('Select role tags for the object.'))
      ->setTransactionType($edge_type)
      ->setMetadataValue('edge:type', $role_edge_type)
      ->setValue($role_phids)
      ->setViewer($viewer);

    $roles_datasource = id(new PhabricatorRoleDatasource())
      ->setViewer($viewer);

    $edit_add = $roles_field->getConduitEditType(self::EDITKEY_ADD)
      ->setConduitDescription(pht('Add role tags.'));

    $edit_set = $roles_field->getConduitEditType(self::EDITKEY_SET)
      ->setConduitDescription(
        pht('Set role tags, overwriting current value.'));

    $edit_rem = $roles_field->getConduitEditType(self::EDITKEY_REMOVE)
      ->setConduitDescription(pht('Remove role tags.'));

    $roles_field->getBulkEditType(self::EDITKEY_ADD)
      ->setBulkEditLabel(pht('Add role tags'))
      ->setDatasource($roles_datasource);

    $roles_field->getBulkEditType(self::EDITKEY_SET)
      ->setBulkEditLabel(pht('Set role tags to'))
      ->setDatasource($roles_datasource);

    $roles_field->getBulkEditType(self::EDITKEY_REMOVE)
      ->setBulkEditLabel(pht('Remove role tags'))
      ->setDatasource($roles_datasource);

    return array(
      $roles_field,
    );
  }

}
