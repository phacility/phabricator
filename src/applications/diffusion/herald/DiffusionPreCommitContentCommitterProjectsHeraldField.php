<?php

final class DiffusionPreCommitContentCommitterProjectsHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.committer.projects';

  public function getHeraldFieldName() {
    return pht("Committer's projects");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();

    $phid = $adapter->getCommitterPHID();
    if (!$phid) {
      return array();
    }

    $viewer = $adapter->getViewer();

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($phid))
      ->execute();

    return mpull($projects, 'getPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

}
