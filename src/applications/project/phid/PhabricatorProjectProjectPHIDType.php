<?php

final class PhabricatorProjectProjectPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PROJ';

  public function getTypeName() {
    return pht('Project');
  }

  public function getTypeIcon() {
    return 'fa-briefcase bluegrey';
  }

  public function newObject() {
    return new PhabricatorProject();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorProjectQuery())
      ->withPHIDs($phids)
      ->needImages(true);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $project = $objects[$phid];

      $name = $project->getDisplayName();
      $id = $project->getID();
      $slug = $project->getPrimarySlug();

      $handle->setName($name);

      if (strlen($slug)) {
        $handle->setObjectName('#'.$slug);
        $handle->setURI("/tag/{$slug}/");
      } else {
        $handle->setURI("/project/view/{$id}/");
      }

      $handle->setImageURI($project->getProfileImageURI());
      $handle->setIcon($project->getDisplayIconIcon());
      $handle->setTagColor($project->getDisplayColor());

      if ($project->isArchived()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

  public static function getProjectMonogramPatternFragment() {
    // NOTE: See some discussion in ProjectRemarkupRule.
    return '[^\s,#]+';
  }

  public function canLoadNamedObject($name) {
    $fragment = self::getProjectMonogramPatternFragment();
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

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($query->getViewer())
      ->withSlugs(array_keys($map))
      ->needSlugs(true)
      ->execute();

    $result = array();
    foreach ($projects as $project) {
      $slugs = $project->getSlugs();
      $slug_strs = mpull($slugs, 'getSlug');
      foreach ($slug_strs as $slug) {
        $slug_map = idx($map, $slug, array());
        foreach ($slug_map as $original) {
          $result[$original] = $project;
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
