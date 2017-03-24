<?php

final class DifferentialQueryConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.query';
  }

  public function getMethodDescription() {
    return pht('Query Differential revisions which match certain criteria.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "differential.revision.search" instead.');
  }

  protected function defineParamTypes() {
    $hash_types = ArcanistDifferentialRevisionHash::getTypes();
    $hash_const = $this->formatStringConstants($hash_types);

    $status_types = array(
      DifferentialRevisionQuery::STATUS_ANY,
      DifferentialRevisionQuery::STATUS_OPEN,
      DifferentialRevisionQuery::STATUS_ACCEPTED,
      DifferentialRevisionQuery::STATUS_CLOSED,
    );
    $status_const = $this->formatStringConstants($status_types);

    $order_types = array(
      DifferentialRevisionQuery::ORDER_MODIFIED,
      DifferentialRevisionQuery::ORDER_CREATED,
    );
    $order_const = $this->formatStringConstants($order_types);

    return array(
      'authors'           => 'optional list<phid>',
      'ccs'               => 'optional list<phid>',
      'reviewers'         => 'optional list<phid>',
      'paths'             => 'optional list<pair<callsign, path>>',
      'commitHashes'      => 'optional list<pair<'.$hash_const.', string>>',
      'status'            => 'optional '.$status_const,
      'order'             => 'optional '.$order_const,
      'limit'             => 'optional uint',
      'offset'            => 'optional uint',
      'ids'               => 'optional list<uint>',
      'phids'             => 'optional list<phid>',
      'subscribers'       => 'optional list<phid>',
      'responsibleUsers'  => 'optional list<phid>',
      'branches'          => 'optional list<string>',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $authors            = $request->getValue('authors');
    $ccs                = $request->getValue('ccs');
    $reviewers          = $request->getValue('reviewers');
    $status             = $request->getValue('status');
    $order              = $request->getValue('order');
    $path_pairs         = $request->getValue('paths');
    $commit_hashes      = $request->getValue('commitHashes');
    $limit              = $request->getValue('limit');
    $offset             = $request->getValue('offset');
    $ids                = $request->getValue('ids');
    $phids              = $request->getValue('phids');
    $subscribers        = $request->getValue('subscribers');
    $responsible_users  = $request->getValue('responsibleUsers');
    $branches           = $request->getValue('branches');

    $query = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser());

    if ($authors) {
      $query->withAuthors($authors);
    }
    if ($ccs) {
      $query->withCCs($ccs);
    }
    if ($reviewers) {
      $query->withReviewers($reviewers);
    }

    if ($path_pairs) {
      $paths = array();
      foreach ($path_pairs as $pair) {
        list($callsign, $path) = $pair;
        $paths[] = $path;
      }

      $path_map = id(new DiffusionPathIDQuery($paths))->loadPathIDs();
      if (count($path_map) != count($paths)) {
        $unknown_paths = array();
        foreach ($paths as $p) {
          if (!idx($path_map, $p)) {
            $unknown_paths[] = $p;
          }
        }
        throw id(new ConduitException('ERR-INVALID-PARAMETER'))
          ->setErrorDescription(
            pht(
              'Unknown paths: %s',
              implode(', ', $unknown_paths)));
      }

      $repos = array();
      foreach ($path_pairs as $pair) {
        list($callsign, $path) = $pair;
        if (!idx($repos, $callsign)) {
          $repos[$callsign] = id(new PhabricatorRepositoryQuery())
            ->setViewer($request->getUser())
            ->withCallsigns(array($callsign))
            ->executeOne();

          if (!$repos[$callsign]) {
            throw id(new ConduitException('ERR-INVALID-PARAMETER'))
              ->setErrorDescription(
                pht(
                  'Unknown repo callsign: %s',
                  $callsign));
          }
        }
        $repo = $repos[$callsign];

        $query->withPath($repo->getID(), idx($path_map, $path));
      }
    }

    if ($commit_hashes) {
      $hash_types = ArcanistDifferentialRevisionHash::getTypes();
      foreach ($commit_hashes as $info) {
        list($type, $hash) = $info;
        if (empty($type) ||
            !in_array($type, $hash_types) ||
            empty($hash)) {
              throw new ConduitException('ERR-INVALID-PARAMETER');
        }
      }
      $query->withCommitHashes($commit_hashes);
    }

    if ($status) {
      $query->withStatus($status);
    }
    if ($order) {
      $query->setOrder($order);
    }
    if ($limit) {
      $query->setLimit($limit);
    }
    if ($offset) {
      $query->setOffset($offset);
    }
    if ($ids) {
      $query->withIDs($ids);
    }
    if ($phids) {
      $query->withPHIDs($phids);
    }
    if ($responsible_users) {
      $query->withResponsibleUsers($responsible_users);
    }
    if ($subscribers) {
      $query->withCCs($subscribers);
    }
    if ($branches) {
      $query->withBranches($branches);
    }

    $query->needReviewers(true);
    $query->needCommitPHIDs(true);
    $query->needDiffIDs(true);
    $query->needActiveDiffs(true);
    $query->needHashes(true);

    $revisions = $query->execute();

    $field_data = $this->loadCustomFieldsForRevisions(
      $request->getUser(),
      $revisions);

    if ($revisions) {
      $ccs = id(new PhabricatorSubscribersQuery())
        ->withObjectPHIDs(mpull($revisions, 'getPHID'))
        ->execute();
    } else {
      $ccs = array();
    }

    $results = array();
    foreach ($revisions as $revision) {
      $diff = $revision->getActiveDiff();
      if (!$diff) {
        continue;
      }

      $id = $revision->getID();
      $phid = $revision->getPHID();

      $result = array(
        'id'                  => $id,
        'phid'                => $phid,
        'title'               => $revision->getTitle(),
        'uri'                 => PhabricatorEnv::getProductionURI('/D'.$id),
        'dateCreated'         => $revision->getDateCreated(),
        'dateModified'        => $revision->getDateModified(),
        'authorPHID'          => $revision->getAuthorPHID(),
        'status'              => $revision->getStatus(),
        'statusName'          =>
          ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
            $revision->getStatus()),
        'properties' => $revision->getProperties(),
        'branch'              => $diff->getBranch(),
        'summary'             => $revision->getSummary(),
        'testPlan'            => $revision->getTestPlan(),
        'lineCount'           => $revision->getLineCount(),
        'activeDiffPHID'      => $diff->getPHID(),
        'diffs'               => $revision->getDiffIDs(),
        'commits'             => $revision->getCommitPHIDs(),
        'reviewers'           => $revision->getReviewerPHIDs(),
        'ccs'                 => idx($ccs, $phid, array()),
        'hashes'              => $revision->getHashes(),
        'auxiliary'           => idx($field_data, $phid, array()),
        'repositoryPHID'      => $diff->getRepositoryPHID(),
      );

      // TODO: This is a hacky way to put permissions on this field until we
      // have first-class support, see T838.
      if ($revision->getAuthorPHID() == $request->getUser()->getPHID()) {
        $result['sourcePath'] = $diff->getSourcePath();
      }

      $results[] = $result;
    }

    return $results;
  }

}
