<?php

final class PhabricatorDifferentialAttachCommitWorkflow
  extends PhabricatorDifferentialManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('attach-commit')
      ->setExamples('**attach-commit** __commit__ __revision__')
      ->setSynopsis(pht('Forcefully attach a commit to a revision.'))
      ->setArguments(
        array(
          array(
            'name' => 'argv',
            'wildcard' => true,
            'help' => pht('Commit, and a revision to attach it to.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $argv = $args->getArg('argv');
    if (count($argv) !== 2) {
      throw new PhutilArgumentUsageException(
        pht('Specify a commit and a revision to attach it to.'));
    }

    $commit_name = head($argv);
    $revision_name = last($argv);

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers(array($commit_name))
      ->executeOne();
    if (!$commit) {
      throw new PhutilArgumentUsageException(
        pht('Commit "%s" does not exist.', $commit_name));
    }

    $revision = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($revision_name))
      ->executeOne();

    if (!$revision) {
      throw new PhutilArgumentUsageException(
        pht('Revision "%s" does not exist.', $revision_name));
    }

    if (!($revision instanceof DifferentialRevision)) {
      throw new PhutilArgumentUsageException(
        pht('Object "%s" must be a Differential revision.', $revision_name));
    }

    // Reload the revision to get the active diff.
    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision->getID()))
      ->needActiveDiffs(true)
      ->executeOne();

    $differential_phid = id(new PhabricatorDifferentialApplication())
      ->getPHID();

    $extraction_engine = id(new DifferentialDiffExtractionEngine())
      ->setViewer($viewer)
      ->setAuthorPHID($differential_phid);

    $content_source = $this->newContentSource();

    $extraction_engine->updateRevisionWithCommit(
      $revision,
      $commit,
      array(),
      $content_source);

    echo tsprintf(
      "%s\n",
      pht(
        'Attached "%s" to "%s".',
        $commit->getMonogram(),
        $revision->getMonogram()));
  }


}
