<?php

final class PhabricatorDifferentialRebuildChangesetsWorkflow
  extends PhabricatorDifferentialManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('rebuild-changesets')
      ->setExamples('**rebuild-changesets** --revision __revision__')
      ->setSynopsis(pht('Rebuild changesets for a revision.'))
      ->setArguments(
        array(
          array(
            'name' => 'revision',
            'param' => 'revision',
            'help' => pht('Revision to rebuild changesets for.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $revision_identifier = $args->getArg('revision');
    if (!$revision_identifier) {
      throw new PhutilArgumentUsageException(
        pht('Specify a revision to rebuild changesets for with "--revision".'));
    }

    $revision = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($revision_identifier))
      ->executeOne();
    if ($revision) {
      if (!($revision instanceof DifferentialRevision)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object "%s" specified by "--revision" must be a Differential '.
            'revision.',
            $revision_identifier));
      }
    } else {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withIDs(array($revision_identifier))
        ->executeOne();
    }

    if (!$revision) {
      throw new PhutilArgumentUsageException(
        pht(
          'No revision "%s" exists.',
          $revision_identifier));
    }

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withRevisionIDs(array($revision->getID()))
      ->execute();

    $changesets = id(new DifferentialChangesetQuery())
      ->setViewer($viewer)
      ->withDiffs($diffs)
      ->needHunks(true)
      ->execute();

    $changeset_groups = mgroup($changesets, 'getDiffID');

    foreach ($changeset_groups as $diff_id => $changesets) {
      echo tsprintf(
        "%s\n",
        pht(
          'Rebuilding %s changeset(s) for diff ID %d.',
          phutil_count($changesets),
          $diff_id));

      foreach ($changesets as $changeset) {
        echo tsprintf(
          "    %s\n",
          $changeset->getFilename());
      }

      id(new DifferentialChangesetEngine())
        ->setViewer($viewer)
        ->rebuildChangesets($changesets);

      foreach ($changesets as $changeset) {
        $changeset->save();
      }

      echo tsprintf(
        "%s\n",
        pht('Done.'));
    }
  }


}
