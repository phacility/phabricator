<?php

final class DiffusionQueryCommitsConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.querycommits';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about commits.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "diffusion.commit.search" instead.');
  }

  protected function defineReturnType() {
    return 'map<string, dict>';
  }

  protected function defineParamTypes() {
    return array(
      'ids'               => 'optional list<int>',
      'phids'             => 'optional list<phid>',
      'names'             => 'optional list<string>',
      'repositoryPHID'    => 'optional phid',
      'needMessages'      => 'optional bool',
      'bypassCache'       => 'optional bool',
    ) + $this->getPagerParamTypes();
  }

  protected function execute(ConduitAPIRequest $request) {
    $need_messages = $request->getValue('needMessages');
    $bypass_cache = $request->getValue('bypassCache');
    $viewer = $request->getUser();

    $query = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->needCommitData(true);

    $repository_phid = $request->getValue('repositoryPHID');
    if ($repository_phid) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($repository_phid))
        ->executeOne();
      if ($repository) {
        $query->withRepository($repository);
        if ($bypass_cache) {
          id(new DiffusionRepositoryClusterEngine())
            ->setViewer($viewer)
            ->setRepository($repository)
            ->synchronizeWorkingCopyBeforeRead();
        }
      }
    }

    $names = $request->getValue('names');
    if ($names) {
      $query->withIdentifiers($names);
    }

    $ids = $request->getValue('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids) {
      $query->withPHIDs($phids);
    }

    $pager = $this->newPager($request);
    $commits = $query->executeWithCursorPager($pager);

    $map = $query->getIdentifierMap();
    $map = mpull($map, 'getPHID');

    $data = array();
    foreach ($commits as $commit) {
      $commit_data = $commit->getCommitData();

      $uri = $commit->getURI();
      $uri = PhabricatorEnv::getProductionURI($uri);

      $dict = array(
        'id' => $commit->getID(),
        'phid' => $commit->getPHID(),
        'repositoryPHID' => $commit->getRepository()->getPHID(),
        'identifier' => $commit->getCommitIdentifier(),
        'epoch' => $commit->getEpoch(),
        'authorEpoch' => $commit_data->getCommitDetail('authorEpoch'),
        'uri' => $uri,
        'isImporting' => !$commit->isImported(),
        'summary' => $commit->getSummary(),
        'authorPHID' => $commit->getAuthorPHID(),
        'committerPHID' => $commit_data->getCommitDetail('committerPHID'),
        'author' => $commit_data->getAuthorName(),
        'authorName' => $commit_data->getCommitDetail('authorName'),
        'authorEmail' => $commit_data->getCommitDetail('authorEmail'),
        'committer' => $commit_data->getCommitDetail('committer'),
        'committerName' => $commit_data->getCommitDetail('committerName'),
        'committerEmail' => $commit_data->getCommitDetail('committerEmail'),
        'hashes' => array(),
      );

      if ($bypass_cache) {
        $lowlevel_commitref = id(new DiffusionLowLevelCommitQuery())
          ->setRepository($commit->getRepository())
          ->withIdentifier($commit->getCommitIdentifier())
          ->execute();

        $dict['authorEpoch'] = $lowlevel_commitref->getAuthorEpoch();
        $dict['author'] = $lowlevel_commitref->getAuthor();
        $dict['authorName'] = $lowlevel_commitref->getAuthorName();
        $dict['authorEmail'] = $lowlevel_commitref->getAuthorEmail();
        $dict['committer'] = $lowlevel_commitref->getCommitter();
        $dict['committerName'] = $lowlevel_commitref->getCommitterName();
        $dict['committerEmail'] = $lowlevel_commitref->getCommitterEmail();

        if ($need_messages) {
          $dict['message'] = $lowlevel_commitref->getMessage();
        }

        foreach ($lowlevel_commitref->getHashes() as $hash) {
          $dict['hashes'][] = array(
            'type' => $hash->getHashType(),
            'value' => $hash->getHashValue(),
          );
        }
      }

      if ($need_messages && !$bypass_cache) {
        $dict['message'] = $commit_data->getCommitMessage();
      }

      $data[$commit->getPHID()] = $dict;
    }

    $result = array(
      'data' => $data,
      'identifierMap' => nonempty($map, (object)array()),
    );

    return $this->addPagerResults($result, $pager);
  }

}
