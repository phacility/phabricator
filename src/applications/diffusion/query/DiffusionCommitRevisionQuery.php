<?php

final class DiffusionCommitRevisionQuery
  extends Phobject {

  public static function loadRevisionMapForCommits(
    PhabricatorUser $viewer,
    array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');

    if (!$commits) {
      return array();
    }

    $commit_phids = mpull($commits, 'getPHID');

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($commit_phids)
      ->withEdgeTypes(
        array(
          DiffusionCommitHasRevisionEdgeType::EDGECONST,
        ));
    $edge_query->execute();

    $revision_phids = $edge_query->getDestinationPHIDs();
    if (!$revision_phids) {
      return array();
    }

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withPHIDs($revision_phids)
      ->execute();
    $revisions = mpull($revisions, null, 'getPHID');

    $map = array();
    foreach ($commit_phids as $commit_phid) {
      $revision_phids = $edge_query->getDestinationPHIDs(
        array(
          $commit_phid,
        ));

      $map[$commit_phid] = array_select_keys($revisions, $revision_phids);
    }

    return $map;
  }

  public static function loadRevisionForCommit(
    PhabricatorUser $viewer,
    PhabricatorRepositoryCommit $commit) {

    $data = $commit->getCommitData();

    $revision_id = $data->getCommitDetail('differential.revisionID');
    if (!$revision_id) {
      return null;
    }

    return id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision_id))
      ->needReviewers(true)
      ->executeOne();
  }


}
