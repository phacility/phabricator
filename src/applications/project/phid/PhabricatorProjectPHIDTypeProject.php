<?php

final class PhabricatorProjectPHIDTypeProject extends PhabricatorPHIDType {

  const TYPECONST = 'PROJ';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Project');
  }

  public function newObject() {
    return new PhabricatorProject();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorProjectQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $project = $objects[$phid];

      $name = $project->getName();
      $id = $project->getID();

      $handle->setName($name);
      $handle->setObjectName('#'.rtrim($project->getPhrictionSlug(), '/'));
      $handle->setURI("/project/view/{$id}/");
    }
  }

  public function canLoadNamedObject($name) {
    // TODO: We should be able to load named projects by hashtag, e.g. "#yolo".
    return false;
  }

}
