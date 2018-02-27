<?php

final class DiffusionCommitCommitterProjectsHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.committer.projects';

  public function getHeraldFieldName() {
    return pht("Committer's projects");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();

    $phid = $object->getCommitData()->getCommitDetail('committerPHID');
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
