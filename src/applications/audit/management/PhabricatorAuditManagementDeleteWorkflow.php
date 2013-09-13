<?php

final class PhabricatorAuditManagementDeleteWorkflow
  extends PhabricatorAuditManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('delete')
      ->setExamples('**delete** [--dry-run] ...')
      ->setSynopsis('Delete audit requests matching parameters.')
      ->setArguments(
        array(
          array(
            'name' => 'dry-run',
            'help' => 'Show what would be deleted, but do not actually delete '.
                      'anything.',
          ),
          array(
            'name' => 'users',
            'param' => 'names',
            'help' => 'Select only audits by a given list of users.',
          ),
          array(
            'name' => 'repositories',
            'param' => 'repos',
            'help' => 'Select only audits in a given list of repositories.',
          ),
          array(
            'name' => 'commits',
            'param' => 'commits',
            'help' => 'Select only audits for the given commits.',
          ),
          array(
            'name' => 'min-commit-date',
            'param' => 'date',
            'help' => 'Select only audits for commits on or after the given '.
                      'date.',
          ),
          array(
            'name' => 'max-commit-date',
            'param' => 'date',
            'help' => 'Select only audits for commits on or before the given '.
                      'date.',
          ),
          array(
            'name' => 'status',
            'param' => 'status',
            'help' => 'Select only audits in the given status. By default, '.
                      'only open audits are selected.',
          ),
          array(
            'name' => 'ids',
            'param' => 'ids',
            'help' => 'Select only audits with the given IDs.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $users = $this->loadUsers($args->getArg('users'));
    $repos = $this->loadRepos($args->getArg('repositories'));
    $commits = $this->loadCommits($args->getArg('commits'));
    $ids = $this->parseList($args->getArg('ids'));

    $status = $args->getArg('status');
    if (!$status) {
      $status = PhabricatorAuditQuery::STATUS_OPEN;
    }

    $min_date = $this->loadDate($args->getArg('min-commit-date'));
    $max_date = $this->loadDate($args->getArg('max-commit-date'));
    if ($min_date && $max_date && ($min_date > $max_date)) {
      throw new PhutilArgumentUsageException(
        "Specified max date must come after specified min date.");
    }

    $is_dry_run = $args->getArg('dry-run');

    $query = id(new PhabricatorAuditQuery())
      ->needCommits(true);

    if ($status) {
      $query->withStatus($status);
    }

    if ($ids) {
      $query->withIDs($ids);
    }

    if ($repos) {
      $query->withRepositoryPHIDs(mpull($repos, 'getPHID'));
    }

    if ($users) {
      $query->withAuditorPHIDs(mpull($users, 'getPHID'));
    }

    if ($commits) {
      $query->withCommitPHIDs(mpull($commits, 'getPHID'));
    }

    $audits = $query->execute();
    $commits = $query->getCommits();

    if ($commits) {
      // TODO: AuditQuery is currently not policy-aware and uses an old query
      // to load commits. Load them in the modern way to get repositories.
      // Remove this after modernizing PhabricatorAuditQuery.
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($commits, 'getPHID'))
        ->execute();
      $commits = mpull($commits, null, 'getPHID');
    }

    foreach ($audits as $key => $audit) {
      $commit = idx($commits, $audit->getCommitPHID());
      if (!$commit) {
        unset($audits[$key]);
        continue;
      }

      if ($min_date && $commit->getEpoch() < $min_date) {
        unset($audits[$key]);
        continue;
      }

      if ($max_date && $commit->getEpoch() > $max_date) {
        unset($audits[$key]);
        continue;
      }
    }

    $console = PhutilConsole::getConsole();

    if (!$audits) {
      $console->writeErr("%s\n", pht("No audits match the query."));
      return 0;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($audits, 'getAuditorPHID'))
      ->execute();


    foreach ($audits as $audit) {
      $commit = idx($commits, $audit->getCommitPHID());

      $console->writeOut(
        "%s\n",
        sprintf(
          "%10d %-16s %-16s %s: %s",
          $audit->getID(),
          $handles[$audit->getAuditorPHID()]->getName(),
          PhabricatorAuditStatusConstants::getStatusName(
            $audit->getAuditStatus()),
          $commit->getRepository()->formatCommitName(
            $commit->getCommitIdentifier()),
          trim($commit->getSummary())));
    }

    if (!$is_dry_run) {
      $message = pht(
        'Really delete these %d audit(s)? They will be permanently deleted '.
        'and can not be recovered.',
        count($audits));
      if ($console->confirm($message)) {
        foreach ($audits as $audit) {
          $id = $audit->getID();
          $console->writeOut("%s\n", pht("Deleting audit %d...", $id));
          $audit->delete();
        }
      }
    }

    return 0;
  }

  private function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  private function loadUsers($users) {
    $users = $this->parseList($users);
    if (!$users) {
      return null;
    }

    $objects = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withUsernames($users)
      ->execute();
    $objects = mpull($objects, null, 'getUsername');

    foreach ($users as $name) {
      if (empty($objects[$name])) {
        throw new PhutilArgumentUsageException(
          pht('No such user with username "%s"!', $name));
      }
    }

    return $objects;
  }

  private function parseList($list) {
    $list = preg_split('/\s*,\s*/', $list);

    foreach ($list as $key => $item) {
      $list[$key] = trim($item);
    }

    foreach ($list as $key => $item) {
      if (!strlen($item)) {
        unset($list[$key]);
      }
    }

    return $list;
  }

  private function loadRepos($callsigns) {
    $callsigns = $this->parseList($callsigns);
    if (!$callsigns) {
      return null;
    }

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withCallsigns($callsigns)
      ->execute();
    $repos = mpull($repos, null, 'getCallsign');

    foreach ($callsigns as $sign) {
      if (empty($repos[$sign])) {
        throw new PhutilArgumentUsageException(
          pht('No such repository with callsign "%s"!', $sign));
      }
    }

    return $repos;
  }

  private function loadDate($date) {
    if (!$date) {
      return null;
    }

    $epoch = strtotime($date);
    if (!$epoch || $epoch < 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to parse date "%s". Use a format like "2000-01-01".',
          $date));
    }

    return $epoch;
  }

  private function loadCommits($commits) {
    $names = $this->parseList($commits);
    if (!$names) {
      return null;
    }

    $query = id(new DiffusionCommitQuery())
      ->setViewer($this->getViewer())
      ->withIdentifiers($names);

    $commits = $query->execute();

    $map = $query->getIdentifierMap();
    foreach ($names as $name) {
      if (empty($map[$name])) {
        throw new PhutilArgumentUsageException(
          pht('No such commit "%s"!', $name));
      }
    }

    return $commits;
  }

}
