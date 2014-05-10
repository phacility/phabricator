<?php

final class ConduitAPI_diffusion_lastmodifiedquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return pht('Get the commits at which paths were last modified.');
  }

  public function defineReturnType() {
    return 'map<string, string>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'paths' => 'required map<string, string>',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $result = array();
    foreach ($request->getValue('paths') as $path => $commit) {
      list($hash) = $repository->execxLocalCommand(
        'log -n1 --format=%%H %s -- %s',
        $commit,
        $path);
      $result[$path] = trim($hash);
    }

    return $result;
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $result = array();
    foreach ($request->getValue('paths') as $path => $commit) {
      $history_result = DiffusionQuery::callConduitWithDiffusionRequest(
        $request->getUser(),
        $drequest,
        'diffusion.historyquery',
        array(
          'commit' => $commit,
          'path' => $path,
          'limit' => 1,
          'offset' => 0,
          'needDirectChanges' => true,
          'needChildChanges' => true,
        ));

      $history_array = DiffusionPathChange::newFromConduit(
        $history_result['pathChanges']);
      if ($history_array) {
        $result[$path] = head($history_array)
          ->getCommit()
          ->getCommitIdentifier();
      }
    }

    return $result;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $result = array();
    foreach ($request->getValue('paths') as $path => $commit) {
      list($hash) = $repository->execxLocalCommand(
        'log --template %s --limit 1 --removed --rev %s -- %s',
        '{node}',
        hgsprintf('reverse(ancestors(%s))',  $commit),
        nonempty(ltrim($path, '/'), '.'));
      $result[$path] = trim($hash);
    }

    return $result;
  }

}
