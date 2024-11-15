<?php

abstract class RoleConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorRoleApplication');
  }

  protected function buildRoleInfoDictionary(PhabricatorRole $role) {
    $results = $this->buildRoleInfoDictionaries(array($role));
    return idx($results, $role->getPHID());
  }

  protected function buildRoleInfoDictionaries(array $roles) {
    assert_instances_of($roles, 'PhabricatorRole');
    if (!$roles) {
      return array();
    }

    $result = array();
    foreach ($roles as $role) {

      $member_phids = $role->getMemberPHIDs();
      $member_phids = array_values($member_phids);

      $role_slugs = $role->getSlugs();
      $role_slugs = array_values(mpull($role_slugs, 'getSlug'));

      $role_icon = $role->getDisplayIconKey();

      $result[$role->getPHID()] = array(
        'id'               => $role->getID(),
        'phid'             => $role->getPHID(),
        'name'             => $role->getName(),
        'profileImagePHID' => $role->getProfileImagePHID(),
        'icon'             => $role_icon,
        'color'            => $role->getColor(),
        'members'          => $member_phids,
        'slugs'            => $role_slugs,
        'dateCreated'      => $role->getDateCreated(),
        'dateModified'     => $role->getDateModified(),
      );
    }

    return $result;
  }

}
