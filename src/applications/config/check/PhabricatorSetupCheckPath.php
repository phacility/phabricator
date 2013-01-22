<?php

final class PhabricatorSetupCheckPath extends PhabricatorSetupCheck {

  protected function executeChecks() {
    // NOTE: We've already appended `environment.append-paths`, so we don't
    // need to explicitly check for it.
    $path = getenv('PATH');

    if (!$path) {
      $summary = pht(
        'The environmental variable $PATH is empty. Phabricator will not '.
        'be able to execute some commands.');

      $message = pht(
        'The environmental variable $PATH is empty. Phabricator needs to '.
        'execute some system commands, like `svn`, `git`, `hg`, and `diff`. '.
        'To execute these commands, the binaries must be available in the '.
        'webserver\'s $PATH. You can set additional paths in Phabricator '.
        'configuration.');

      $this
        ->newIssue('config.environment.append-paths')
        ->setName(pht('$PATH Not Set'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPhabricatorConfig('environment.append-paths');
    }
  }
}
