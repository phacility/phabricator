<?php

final class PhabricatorRoleRolePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ROLE';

  public function getTypeName() {
    return pht('Role');
  }

  public function getTypeIcon() {
    return 'fa-briefcase bluegrey';
  }

  public function newObject() {
    return new PhabricatorRole();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRoleQuery())
      ->withPHIDs($phids)
      ->needImages(true);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $role = $objects[$phid];

      $name = $role->getDisplayName();
      $id = $role->getID();
      $slug = $role->getPrimarySlug();

      $handle->setName($name);

      if ($slug !== null && $slug !== '') {
        $handle->setObjectName('#'.$slug);
        $handle->setMailStampName('#'.$slug);
        $handle->setURI("/tag/{$slug}/");
      } else {
        // We set the name to the role's PHID to avoid a parse error when a
        // role has no hashtag (as is the case with milestones by default).
        // See T12659 for more details.
        $handle->setCommandLineObjectName($role->getPHID());
        $handle->setURI("/role/view/{$id}/");
      }

      $handle->setImageURI($role->getProfileImageURI());
      $handle->setIcon($role->getDisplayIconIcon());
      $handle->setTagColor($role->getDisplayColor());

      if ($role->isArchived()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

  public static function getRoleMonogramPatternFragment() {
    // NOTE: See some discussion in RoleRemarkupRule.
    return '[^\s,#]+';
  }

  public function canLoadNamedObject($name) {
    $fragment = self::getRoleMonogramPatternFragment();
    return preg_match('/^#'.$fragment.'$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    // If the user types "#YoloSwag", we still want to match "#yoloswag", so
    // we normalize, query, and then map back to the original inputs.

    $map = array();
    foreach ($names as $key => $slug) {
      $map[$this->normalizeSlug(substr($slug, 1))][] = $slug;
    }

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($query->getViewer())
      ->withSlugs(array_keys($map))
      ->needSlugs(true)
      ->execute();

    $result = array();
    foreach ($roles as $role) {
      $slugs = $role->getSlugs();
      $slug_strs = mpull($slugs, 'getSlug');
      foreach ($slug_strs as $slug) {
        $slug_map = idx($map, $slug, array());
        foreach ($slug_map as $original) {
          $result[$original] = $role;
        }
      }
    }

    return $result;
  }

  private function normalizeSlug($slug) {
    // NOTE: We're using phutil_utf8_strtolower() (and not PhabricatorSlug's
    // normalize() method) because this normalization should be only somewhat
    // liberal. We want "#YOLO" to match against "#yolo", but "#\\yo!!lo"
    // should not. normalize() strips out most punctuation and leads to
    // excessively aggressive matches.

    return phutil_utf8_strtolower($slug);
  }

}
