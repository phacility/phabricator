<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_lastmodifiedquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return
      'Get last modified information from a repository for a specific commit '.
      'at a specific path.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
      'path' => 'required string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    list($commit, $commit_data) = parent::getResult($request);
    if ($commit) {
      $commit = $commit->toDictionary();
    }
    if ($commit_data) {
      $commit_data = $commit_data->toDictionary();
    }
    return array(
      'commit' => $commit,
      'commitData' => $commit_data);
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    list($hash) = $repository->execxLocalCommand(
      'log -n1 --format=%%H %s -- %s',
      $drequest->getCommit(),
      $drequest->getPath());
    $hash = trim($hash);

    return $this->loadDataFromHash($hash);
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    $history_result = DiffusionQuery::callConduitWithDiffusionRequest(
      $request->getUser(),
      $drequest,
      'diffusion.historyquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $path,
        'limit' => 1,
        'offset' => 0,
        'needDirectChanges' => true,
        'needChildChanges' => true));
    $history_array = DiffusionPathChange::newFromConduit(
      $history_result['pathChanges']);

    if (!$history_array) {
      return array(array(), array());
    }

    $history = reset($history_array);

    return array($history->getCommit(), $history->getCommitData());
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    list($hash) = $repository->execxLocalCommand(
      'log --template %s --limit 1 --removed --rev %s -- %s',
      '{node}',
      hgsprintf('reverse(ancestors(%s))',  $drequest->getCommit()),
      nonempty(ltrim($path, '/'), '.'));

    return $this->loadDataFromHash($hash);
  }

  private function loadDataFromHash($hash) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $hash);

    if ($commit) {
      $commit_data = $commit->loadCommitData();
    } else {
      $commit = array();
      $commit_data = array();
    }

    return array($commit, $commit_data);
  }

}
