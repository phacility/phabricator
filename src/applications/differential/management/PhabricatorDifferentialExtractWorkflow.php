<?php

final class PhabricatorDifferentialExtractWorkflow
  extends PhabricatorDifferentialManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('extract')
      ->setExamples('**extract** __commit__')
      ->setSynopsis(pht('Extract a diff from a commit.'))
      ->setArguments(
        array(
          array(
            'name' => 'extract',
            'wildcard' => true,
            'help' => pht('Commit to extract.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $extract = $args->getArg('extract');

    if (!$extract) {
      throw new PhutilArgumentUsageException(
        pht('Specify a commit to extract the diff from.'));
    }

    if (count($extract) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one commit to extract.'));
    }

    $extract = head($extract);

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers(array($extract))
      ->executeOne();

    if (!$commit) {
      throw new PhutilArgumentUsageException(
        pht(
          'Commit "%s" is not valid.',
          $extract));
    }

    $diff = id(new DifferentialDiffExtractionEngine())
      ->setViewer($viewer)
      ->newDiffFromCommit($commit);

    $uri = PhabricatorEnv::getProductionURI($diff->getURI());

    echo tsprintf(
      "%s\n\n    %s\n",
      pht('Extracted diff from "%s":', $extract),
      $uri);
  }


}
