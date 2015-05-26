<?php

final class PhabricatorAuditManagementDeleteWorkflow
  extends PhabricatorAuditManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('delete')
      ->setExamples('**delete** [--dry-run] ...')
      ->setSynopsis(pht('Delete audit requests matching parameters.'))
      ->setArguments(
        array(
          array(
            'name' => 'dry-run',
            'help' => pht(
              'Show what would be deleted, but do not actually delete '.
              'anything.'),
          ),
          array(
            'name' => 'users',
            'param' => 'names',
            'help' => pht('Select only audits by a given list of users.'),
          ),
          array(
            'name' => 'repositories',
            'param' => 'repos',
            'help' => pht(
              'Select only audits in a given list of repositories.'),
          ),
          array(
            'name' => 'commits',
            'param' => 'commits',
            'help' => pht('Select only audits for the given commits.'),
          ),
          array(
            'name' => 'min-commit-date',
            'param' => 'date',
            'help' => pht(
              'Select only audits for commits on or after the given date.'),
          ),
          array(
            'name' => 'max-commit-date',
            'param' => 'date',
            'help' => pht(
              'Select only audits for commits on or before the given date.'),
          ),
          array(
            'name' => 'status',
            'param' => 'status',
            'help' => pht(
              'Select only audits in the given status. By default, '.
              'only open audits are selected.'),
          ),
          array(
            'name' => 'ids',
            'param' => 'ids',
            'help' => pht('Select only audits with the given IDs.'),
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
      $status = DiffusionCommitQuery::AUDIT_STATUS_OPEN;
    }

    $min_date = $this->loadDate($args->getArg('min-commit-date'));
    $max_date = $this->loadDate($args->getArg('max-commit-date'));
    if ($min_date && $max_date && ($min_date > $max_date)) {
      throw new PhutilArgumentUsageException(
        pht('Specified maximum date must come after specified minimum date.'));
    }

    $is_dry_run = $args->getArg('dry-run');

    $query = id(new DiffusionCommitQuery())
      ->setViewer($this->getViewer())
      ->needAuditRequests(true);

    if ($status) {
      $query->withAuditStatus($status);
    }

    $id_map = array();
    if ($ids) {
      $id_map = array_fuse($ids);
      $query->withAuditIDs($ids);
    }

    if ($repos) {
      $query->withRepositoryIDs(mpull($repos, 'getID'));
    }

    $auditor_map = array();
    if ($users) {
      $auditor_map = array_fuse(mpull($users, 'getPHID'));
      $query->withAuditorPHIDs($auditor_map);
    }

    if ($commits) {
      $query->withPHIDs(mpull($commits, 'getPHID'));
    }

    $commits = $query->execute();
    $commits = mpull($commits, null, 'getPHID');
    $audits = array();
    foreach ($commits as $commit) {
      $commit_audits = $commit->getAudits();
      foreach ($commit_audits as $key => $audit) {
        if ($id_map && empty($id_map[$audit->getID()])) {
          unset($commit_audits[$key]);
          continue;
        }

        if ($auditor_map && empty($auditor_map[$audit->getAuditorPHID()])) {
          unset($commit_audits[$key]);
          continue;
        }

        if ($min_date && $commit->getEpoch() < $min_date) {
          unset($commit_audits[$key]);
          continue;
        }

        if ($max_date && $commit->getEpoch() > $max_date) {
          unset($commit_audits[$key]);
          continue;
        }
      }
      $audits[] = $commit_audits;
    }
    $audits = array_mergev($audits);

    $console = PhutilConsole::getConsole();

    if (!$audits) {
      $console->writeErr("%s\n", pht('No audits match the query.'));
      return 0;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($audits, 'getAuditorPHID'))
      ->execute();


    foreach ($audits as $audit) {
      $commit = $commits[$audit->getCommitPHID()];

      $console->writeOut(
        "%s\n",
        sprintf(
          '%10d %-16s %-16s %s: %s',
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
          $console->writeOut("%s\n", pht('Deleting audit %d...', $id));
          $audit->delete();
        }
      }
    }

    return 0;
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
          'Unable to parse date "%s". Use a format like "%s".',
          $date,
          '2000-01-01'));
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
