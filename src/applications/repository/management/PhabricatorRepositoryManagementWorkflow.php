<?php

abstract class PhabricatorRepositoryManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function loadRepositories(PhutilArgumentParser $args, $param) {
    $identifiers = $args->getArg($param);

    if (!$identifiers) {
      return null;
    }

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withIdentifiers($identifiers);

    $query->execute();

    $map = $query->getIdentifierMap();
    foreach ($identifiers as $identifier) {
      if (empty($map[$identifier])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Repository "%s" does not exist!',
            $identifier));
      }
    }

    // Reorder repositories according to argument order.
    $repositories = array_select_keys($map, $identifiers);

    return array_values($repositories);
  }

  protected function loadCommits(PhutilArgumentParser $args, $param) {
    $names = $args->getArg($param);
    if (!$names) {
      return null;
    }

    return $this->loadNamedCommits($names);
  }

  protected function loadNamedCommit($name) {
    $map = $this->loadNamedCommits(array($name));
    return $map[$name];
  }

  protected function loadNamedCommits(array $names) {
    $query = id(new DiffusionCommitQuery())
      ->setViewer($this->getViewer())
      ->withIdentifiers($names);

    $query->execute();
    $map = $query->getIdentifierMap();

    foreach ($names as $name) {
      if (empty($map[$name])) {
        throw new PhutilArgumentUsageException(
          pht('Commit "%s" does not exist or is ambiguous.', $name));
      }
    }

    return $map;
  }


}
