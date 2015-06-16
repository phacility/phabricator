<?php

final class PhabricatorRepositoryManagementMarkImportedWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('mark-imported')
      ->setExamples('**mark-imported** __repository__ ...')
      ->setSynopsis(pht('Mark __repository__, named by callsign, as imported.'))
      ->setArguments(
        array(
          array(
            'name'        => 'mark-not-imported',
            'help'        => pht('Instead, mark repositories as NOT imported.'),
          ),
          array(
            'name'        => 'repos',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadRepositories($args, 'repos');

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to mark imported, by callsign.'));
    }

    $new_importing_value = (bool)$args->getArg('mark-not-imported');

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $callsign = $repo->getCallsign();

      if ($repo->isImporting() && $new_importing_value) {
        $console->writeOut(
          "%s\n",
          pht("Repository '%s' is already importing.", $callsign));
      } else if (!$repo->isImporting() && !$new_importing_value) {
        $console->writeOut(
          "%s\n",
          pht("Repository '%s' is already imported.", $callsign));
      } else {
        if ($new_importing_value) {
          $console->writeOut(
            "%s\n",
            pht("Marking repository '%s' as importing.", $callsign));
        } else {
          $console->writeOut(
            "%s\n",
            pht("Marking repository '%s' as imported.", $callsign));
        }

        $repo->setDetail('importing', $new_importing_value);
        $repo->save();
      }
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
