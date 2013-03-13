<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_getrecentcommitsbypath_Method
  extends ConduitAPI_diffusion_Method {

  const DEFAULT_LIMIT = 10;

  public function getMethodDescription() {
    return "Get commit identifiers for recent commits affecting a given path.";
  }

  public function defineParamTypes() {
    return array(
      'callsign' => 'required string',
      'path' => 'required string',
      'limit' => 'optional int',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<string>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'callsign'  => $request->getValue('callsign'),
        'path'      => $request->getValue('path'),
      ));

    $limit = nonempty(
      $request->getValue('limit'),
      self::DEFAULT_LIMIT);

    $history = DiffusionHistoryQuery::newFromDiffusionRequest($drequest)
    ->setLimit($limit)
    ->needDirectChanges(true)
    ->needChildChanges(true)
    ->loadHistory();

    $raw_commit_identifiers = mpull($history, 'getCommitIdentifier');
    $result = array();
    foreach ($raw_commit_identifiers as $id) {
      $result[] = 'r'.$request->getValue('callsign').$id;
    }
    return $result;
  }
}
