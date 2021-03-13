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
      $query->withStatuses(array($status));
    }

    $id_map = array();
    if ($ids) {
      $id_map = array_fuse($ids);
      $query->withAuditIDs($ids);
    }

    if ($repos) {
      $query->withRepositoryIDs(mpull($repos, 'getID'));

      // See T13457. If we're iterating over commits in a single large
      // repository, the lack of a "<repositoryID, [id]>" key can slow things
      // down. Iterate in a specific order to use a key which is present
      // on the table ("<repositoryID, epoch, [id]>").
      $query->setOrderVector(array('-epoch', '-id'));
    }

    $auditor_map = array();
    if ($users) {
      $auditor_map = array_fuse(mpull($users, 'getPHID'));
      $query->withAuditorPHIDs($auditor_map);
    }

    if ($commits) {
      $query->withPHIDs(mpull($commits, 'getPHID'));
    }

    $commit_iterator = id(new PhabricatorQueryIterator($query));

    // See T13457. We may be examining many commits; each commit is small so
    // we can safely increase the page size to improve performance a bit.
    $commit_iterator->setPageSize(1000);

    $audits = array();
    foreach ($commit_iterator as $commit) {
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

      if (!$commit_audits) {
        continue;
      }

      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($commit_audits, 'getAuditorPHID'))
        ->execute();

      foreach ($commit_audits as $audit) {
        $audit_id = $audit->getID();
        $status = $audit->getAuditRequestStatusObject();

        $description = sprintf(
          '%10d %-16s %-16s %s: %s',
          $audit_id,
          $handles[$audit->getAuditorPHID()]->getName(),
          $status->getStatusName(),
          $commit->getRepository()->formatCommitName(
            $commit->getCommitIdentifier()),
          trim($commit->getSummary()));

        $audits[] = array(
          'auditID' => $audit_id,
          'commitPHID' => $commit->getPHID(),
          'description' => $description,
        );
      }
    }

    if (!$audits) {
      echo tsprintf(
        "%s\n",
        pht('No audits match the query.'));
      return 0;
    }

    foreach ($audits as $audit_spec) {
      echo tsprintf(
        "%s\n",
        $audit_spec['description']);
    }

    if ($is_dry_run) {
      echo tsprintf(
        "%s\n",
        pht('This is a dry run, so no changes will be made.'));
      return 0;
    }

    $message = pht(
      'Really delete these %s audit(s)? They will be permanently deleted '.
      'and can not be recovered.',
      phutil_count($audits));
    if (!phutil_console_confirm($message)) {
      echo tsprintf(
        "%s\n",
        pht('User aborted the workflow.'));
      return 1;
    }

    $audits_by_commit = igroup($audits, 'commitPHID');
    foreach ($audits_by_commit as $commit_phid => $audit_specs) {
      $audit_ids = ipull($audit_specs, 'auditID');

      $audits = id(new PhabricatorRepositoryAuditRequest())->loadAllWhere(
        'id IN (%Ld)',
        $audit_ids);

      foreach ($audits as $audit) {
        $id = $audit->getID();

        echo tsprintf(
          "%s\n",
          pht('Deleting audit %d...', $id));

        $audit->delete();
      }

      $this->synchronizeCommitAuditState($commit_phid);
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

  private function loadRepos($identifiers) {
    $identifiers = $this->parseList($identifiers);
    if (!$identifiers) {
      return null;
    }

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withIdentifiers($identifiers);

    $repos = $query->execute();

    $map = $query->getIdentifierMap();
    foreach ($identifiers as $identifier) {
      if (empty($map[$identifier])) {
        throw new PhutilArgumentUsageException(
          pht('No repository "%s" exists!', $identifier));
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
