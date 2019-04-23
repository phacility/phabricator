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

  public static function loadRevertedObjects(
    PhabricatorUser $viewer,
    $source_object,
    array $object_names,
    PhabricatorRepository $repository_scope = null) {

    // Fetch commits first, since we need to load data on commits in order
    // to identify associated revisions later on.
    $commit_query = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers($object_names)
      ->needCommitData(true);

    // If we're acting in a specific repository, only allow commits in that
    // repository to be affected: when commit X reverts commit Y by hash, we
    // only want to revert commit Y in the same repository, even if other
    // repositories have a commit with the same hash.
    if ($repository_scope) {
      $commit_query->withRepository($repository_scope);
    }

    $objects = $commit_query->execute();

    $more_objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($object_names)
      ->withTypes(
        array(
          DifferentialRevisionPHIDType::TYPECONST,
        ))
      ->execute();
    foreach ($more_objects as $object) {
      $objects[] = $object;
    }

    // See PHI1008 and T13276. If something reverts commit X, we also revert
    // any associated revision.

    // For now, we don't try to find associated commits if something reverts
    // revision Y. This is less common, although we could make more of an
    // effort in the future.

    foreach ($objects as $object) {
      if (!($object instanceof PhabricatorRepositoryCommit)) {
        continue;
      }

      // NOTE: If our object "reverts X", where "X" is a commit hash, it is
      // possible that "X" will not have parsed yet, so we'll fail to find
      // a revision even though one exists.

      // For now, do nothing. It's rare to push a commit which reverts some
      // commit "X" before "X" has parsed, so we expect this to be unusual.

      $revision = self::loadRevisionForCommit(
        $viewer,
        $object);
      if ($revision) {
        $objects[] = $revision;
      }
    }

    $objects = mpull($objects, null, 'getPHID');

    // Prevent an object from reverting itself, although this would be very
    // clever in Git or Mercurial.
    unset($objects[$source_object->getPHID()]);

    return $objects;
  }

}
