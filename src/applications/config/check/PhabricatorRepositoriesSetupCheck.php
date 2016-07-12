<?php

final class PhabricatorRepositoriesSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {

    $cluster_services = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withServiceTypes(
        array(
          AlmanacClusterRepositoryServiceType::SERVICETYPE,
        ))
      ->setLimit(1)
      ->execute();
    if ($cluster_services) {
      // If cluster repository services are defined, these checks aren't useful
      // because some nodes (like web nodes) will usually not have any local
      // repository information.

      // Errors with this configuration will still be detected by checks on
      // individual repositories.
      return;
    }

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
        'The path for local repositories does not exist, or is not '.
        'readable by the webserver.');
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
