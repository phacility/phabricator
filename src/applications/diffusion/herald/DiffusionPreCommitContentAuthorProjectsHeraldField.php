<?php

final class DiffusionPreCommitContentAuthorProjectsHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.author.projects';

  public function getHeraldFieldName() {
    return pht("Author's projects");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();

    $phid = $adapter->getAuthorPHID();
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
