<?php

final class PhabricatorRepositoryManagementHintWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('hint')
      ->setExamples('**hint** [options] ...')
      ->setSynopsis(
        pht(
          'Write hints about unusual (rewritten or unreadable) commits.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    echo tsprintf(
      "%s\n",
      pht('Reading list of hints from stdin...'));

    $hints = file_get_contents('php://stdin');
    if ($hints === false) {
      throw new PhutilArgumentUsageException(pht('Failed to read stdin.'));
    }

    try {
      $hints = phutil_json_decode($hints);
    } catch (Exception $ex) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected a list of hints in JSON format: %s',
          $ex->getMessage()));
    }

    $repositories = array();
    foreach ($hints as $idx => $hint) {
      if (!is_array($hint)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Each item in the list of hints should be a JSON object, but '.
            'the item at index "%s" is not.',
            $idx));
      }

      try {
        PhutilTypeSpec::checkMap(
          $hint,
          array(
            'repository' => 'string|int',
            'old' => 'string',
            'new' => 'optional string|null',
            'hint' => 'string',
          ));
      } catch (Exception $ex) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unexpected hint format at index "%s": %s',
            $idx,
            $ex->getMessage()));
      }

      $repository_identifier = $hint['repository'];
      $repository = idx($repositories, $repository_identifier);
      if (!$repository) {
        $repository = id(new PhabricatorRepositoryQuery())
          ->setViewer($viewer)
          ->withIdentifiers(array($repository_identifier))
          ->executeOne();
        if (!$repository) {
          throw new PhutilArgumentUsageException(
            pht(
              'Repository identifier "%s" (in hint at index "%s") does not '.
              'identify a valid repository.',
              $repository_identifier,
              $idx));
        }

        $repositories[$repository_identifier] = $repository;
      }

      PhabricatorRepositoryCommitHint::updateHint(
        $repository->getPHID(),
        $hint['old'],
        idx($hint, 'new'),
        $hint['hint']);

      echo tsprintf(
        "%s\n",
        pht(
          'Updated hint for "%s".',
          $hint['old']));
    }
  }

}
