<?php

final class DiffusionGetCommitsConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.getcommits';
  }

  public function getMethodDescription() {
    return pht('Retrieve Diffusion commit information.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht('Obsoleted by diffusion.querycommits.');
  }

  protected function defineParamTypes() {
    return array(
      'commits' => 'required list<string>',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();

    $commits = $request->getValue('commits');
    $commits = array_fill_keys($commits, array());
    foreach ($commits as $name => $info) {
      $matches = null;
      if (!preg_match('/^r([A-Z]+)([0-9a-f]+)\z/', $name, $matches)) {
        $results[$name] = array(
          'error' => 'ERR-UNPARSEABLE',
        );
        unset($commits[$name]);
        continue;
      }
      $commits[$name] = array(
        'callsign'          => $matches[1],
        'commitIdentifier'  => $matches[2],
      );
    }

    if (!$commits) {
      return $results;
    }

    $callsigns = ipull($commits, 'callsign');
    $callsigns = array_unique($callsigns);
    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($request->getUser())
      ->withCallsigns($callsigns)
      ->execute();
    $repos = mpull($repos, null, 'getCallsign');

    foreach ($commits as $name => $info) {
      $repo = idx($repos, $info['callsign']);
      if (!$repo) {
        $results[$name] = $info + array(
          'error' => 'ERR-UNKNOWN-REPOSITORY',
        );
        unset($commits[$name]);
        continue;
      }
      $commits[$name] += array(
        'repositoryPHID' => $repo->getPHID(),
        'repositoryID' => $repo->getID(),
      );
    }

    if (!$commits) {
      return $results;
    }

    // Execute a complicated query to figure out the primary commit information
    // for each referenced commit.
    $cdata = $this->queryCommitInformation($commits, $repos);

    // We've built the queries so that each row also has the identifier we used
    // to select it, which might be a git prefix rather than a full identifier.
    $ref_map = ipull($cdata, 'commitIdentifier', 'commitRef');

    $cobjs = id(new PhabricatorRepositoryCommit())->loadAllFromArray($cdata);
    $cobjs = mgroup($cobjs, 'getRepositoryID', 'getCommitIdentifier');
    foreach ($commits as $name => $commit) {

      // Expand short git names into full identifiers. For SVN this map is just
      // the identity.
      $full_identifier = idx($ref_map, $commit['commitIdentifier']);

      $repo_id = $commit['repositoryID'];
      unset($commits[$name]['repositoryID']);

      if (empty($full_identifier) ||
          empty($cobjs[$commit['repositoryID']][$full_identifier])) {
        $results[$name] = $commit + array(
          'error' => 'ERR-UNKNOWN-COMMIT',
        );
        unset($commits[$name]);
        continue;
      }

      $cobj_arr = $cobjs[$commit['repositoryID']][$full_identifier];
      $cobj = head($cobj_arr);

      $commits[$name] += array(
        'epoch'             => $cobj->getEpoch(),
        'commitPHID'        => $cobj->getPHID(),
        'commitID'          => $cobj->getID(),
      );

      // Upgrade git short references into full commit identifiers.
      $identifier = $cobj->getCommitIdentifier();
      $commits[$name]['commitIdentifier'] = $identifier;

      $callsign = $commits[$name]['callsign'];
      $uri = "/r{$callsign}{$identifier}";
      $commits[$name]['uri'] = PhabricatorEnv::getProductionURI($uri);
    }

    if (!$commits) {
      return $results;
    }

    $commits = $this->addRepositoryCommitDataInformation($commits);
    $commits = $this->addDifferentialInformation($commits, $request);
    $commits = $this->addManiphestInformation($commits);

    foreach ($commits as $name => $commit) {
      $results[$name] = $commit;
    }

    return $results;
  }

  /**
   * Retrieve primary commit information for all referenced commits.
   */
  private function queryCommitInformation(array $commits, array $repos) {
    assert_instances_of($repos, 'PhabricatorRepository');
    $conn_r = id(new PhabricatorRepositoryCommit())->establishConnection('r');
    $repos = mpull($repos, null, 'getID');

    $groups = array();
    foreach ($commits as $name => $commit) {
      $groups[$commit['repositoryID']][] = $commit['commitIdentifier'];
    }

    // NOTE: MySQL goes crazy and does a massive table scan if we build a more
    // sensible version of this query. Make sure the query plan is OK if you
    // attempt to reduce the craziness here. METANOTE: The addition of prefix
    // selection for Git further complicates matters.
    $query = array();
    $commit_table = id(new PhabricatorRepositoryCommit())->getTableName();

    foreach ($groups as $repository_id => $identifiers) {
      $vcs = $repos[$repository_id]->getVersionControlSystem();
      $is_git = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);
      if ($is_git) {
        foreach ($identifiers as $identifier) {
          if (strlen($identifier) < 7) {
            // Don't bother with silly stuff like 'rX2', which will select
            // 1/16th of all commits. Note that with length 7 we'll still get
            // collisions in repositories at the tens-of-thousands-of-commits
            // scale.
            continue;
          }
          $query[] = qsprintf(
            $conn_r,
            'SELECT %T.*, %s commitRef
              FROM %T WHERE repositoryID = %d
              AND commitIdentifier LIKE %>',
            $commit_table,
            $identifier,
            $commit_table,
            $repository_id,
            $identifier);
        }
      } else {
        $query[] = qsprintf(
          $conn_r,
          'SELECT %T.*, commitIdentifier commitRef
            FROM %T WHERE repositoryID = %d
            AND commitIdentifier IN (%Ls)',
          $commit_table,
          $commit_table,
          $repository_id,
          $identifiers);
      }
    }

    return queryfx_all(
      $conn_r,
      '%Q',
      implode(' UNION ALL ', $query));
  }

  /**
   * Enhance the commit list with RepositoryCommitData information.
   */
  private function addRepositoryCommitDataInformation(array $commits) {
    $commit_ids = ipull($commits, 'commitID');

    $data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
      'commitID in (%Ld)',
      $commit_ids);
    $data = mpull($data, null, 'getCommitID');

    foreach ($commits as $name => $commit) {
      if (isset($data[$commit['commitID']])) {
        $dobj = $data[$commit['commitID']];
        $commits[$name] += array(
          'commitMessage' => $dobj->getCommitMessage(),
          'commitDetails' => $dobj->getCommitDetails(),
        );
      }

      // Remove this information so we don't expose it via the API since
      // external services shouldn't be storing internal Commit IDs.
      unset($commits[$name]['commitID']);
    }

    return $commits;
  }

  /**
   * Enhance the commit list with Differential information.
   */
  private function addDifferentialInformation(
    array $commits,
    ConduitAPIRequest $request) {

    $commit_phids = ipull($commits, 'commitPHID');

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withCommitPHIDs($commit_phids)
      ->needCommitPHIDs(true)
      ->execute();
    $rev_phid_commit_phids_map = mpull($revisions, 'getCommitPHIDs', 'getPHID');
    $revisions = mpull($revisions, null, 'getPHID');
    foreach ($rev_phid_commit_phids_map as $rev_phid => $commit_phids) {
      foreach ($commits as $name => $commit) {
        $commit_phid = $commit['commitPHID'];
        if (in_array($commit_phid, $commit_phids)) {
          $revision = $revisions[$rev_phid];
          $commits[$name] += array(
            'differentialRevisionID'    => 'D'.$revision->getID(),
            'differentialRevisionPHID'  => $revision->getPHID(),
          );
        }
      }
    }

    return $commits;
  }

  /**
   * Enhances the commits list with Maniphest information.
   */
  private function addManiphestInformation(array $commits) {
    $task_type = DiffusionCommitHasTaskEdgeType::EDGECONST;

    $commit_phids = ipull($commits, 'commitPHID');

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($commit_phids)
      ->withEdgeTypes(array($task_type));

    $edges = $edge_query->execute();

    foreach ($commits as $name => $commit) {
      $task_phids = $edge_query->getDestinationPHIDs(
        array($commit['commitPHID']),
        array($task_type));

      $commits[$name] += array(
        'taskPHIDs' => $task_phids,
      );
    }

    return $commits;
  }

}
