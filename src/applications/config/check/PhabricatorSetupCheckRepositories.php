<?php

final class PhabricatorSetupCheckRepositories extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $repo_path = PhabricatorEnv::getEnvConfig('repository.default-local-path');

    if (!$repo_path) {
      $summary = pht(
        "The configuration option '%s' is not set.",
        'repository.default-local-path');
      $this->newIssue('repository.default-local-path.empty')
        ->setName(pht('Missing Repository Local Path'))
        ->setSummary($summary)
        ->addPhabricatorConfig('repository.default-local-path');
      return;
    }

    if (!Filesystem::pathExists($repo_path)) {
      $summary = pht(
        "The path for local repositories does not exist, or is not ".
        "readable by the webserver.");
      $message = pht(
        "The directory for local repositories (%s) does not exist, or is not ".
        "readable by the webserver. Phabricator uses this directory to store ".
        "information about repositories. If this directory does not exist, ".
        "create it:\n\n".
        "%s\n".
        "If this directory exists, make it readable to the webserver. You ".
        "can also edit the configuration below to use some other directory.",
        phutil_tag('tt', array(), $repo_path),
        phutil_tag('pre', array(), csprintf('$ mkdir -p %s', $repo_path)));

      $this->newIssue('repository.default-local-path.empty')
        ->setName(pht('Missing Repository Local Path'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPhabricatorConfig('repository.default-local-path');
    }

  }

}
