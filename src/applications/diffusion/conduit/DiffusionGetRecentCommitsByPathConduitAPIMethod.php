<?php

final class DiffusionGetRecentCommitsByPathConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  const DEFAULT_LIMIT = 10;

  public function getAPIMethodName() {
    return 'diffusion.getrecentcommitsbypath';
  }

  public function getMethodDescription() {
    return 'Get commit identifiers for recent commits affecting a given path.';
  }

  protected function defineParamTypes() {
    return array(
      'callsign' => 'required string',
      'path' => 'required string',
      'branch' => 'optional string',
      'limit' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<string>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $request->getUser(),
        'callsign' => $request->getValue('callsign'),
        'path' => $request->getValue('path'),
        'branch' => $request->getValue('branch'),
      ));

    $limit = nonempty(
      $request->getValue('limit'),
      self::DEFAULT_LIMIT);

    $history_result = DiffusionQuery::callConduitWithDiffusionRequest(
      $request->getUser(),
      $drequest,
      'diffusion.historyquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
        'offset' => 0,
        'limit' => $limit,
        'needDirectChanges' => true,
        'needChildChanges' => true,
      ));
    $history = DiffusionPathChange::newFromConduit(
      $history_result['pathChanges']);

    $raw_commit_identifiers = mpull($history, 'getCommitIdentifier');
    $result = array();
    foreach ($raw_commit_identifiers as $id) {
      $result[] = 'r'.$request->getValue('callsign').$id;
    }
    return $result;
  }

}
