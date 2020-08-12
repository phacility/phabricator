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
        'authorEpoch' => $commit_data->getAuthorEpoch(),
        'uri' => $uri,
        'isImporting' => !$commit->isImported(),
        'summary' => $commit->getSummary(),
        'authorPHID' => $commit->getAuthorPHID(),
        'committerPHID' => $commit_data->getCommitDetail('committerPHID'),
        'author' => $commit_data->getAuthorString(),
        'authorName' => $commit_data->getAuthorDisplayName(),
        'authorEmail' => $commit_data->getAuthorEmail(),
        'committer' => $commit_data->getCommitterString(),
        'committerName' => $commit_data->getCommitterDisplayName(),
        'committerEmail' => $commit_data->getCommitterEmail(),
        'hashes' => array(),
      );

      if ($need_messages) {
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
