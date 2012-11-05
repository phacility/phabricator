<?php

/**
 * @group conduit
 */
final class ConduitAPI_chatlog_query_Method
  extends ConduitAPI_chatlog_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Retrieve chatter.";
  }

  public function defineParamTypes() {
    return array(
      'channels' => 'optional list<string>',
      'limit'    => 'optional int (default = 100)',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {

    $query = new PhabricatorChatLogQuery();

    $channels = $request->getValue('channels');
    if ($channels) {
      $query->withChannels($channels);
    }

    $limit = $request->getValue('limit');
    if (!$limit) {
      $limit = 100;
    }
    $query->setLimit($limit);

    $logs = $query->execute();

    $results = array();
    foreach ($logs as $log) {
      $results[] = array(
        'channel'       => $log->getChannel(),
        'epoch'         => $log->getEpoch(),
        'author'        => $log->getAuthor(),
        'type'          => $log->getType(),
        'message'       => $log->getMessage(),
        'loggedByPHID'  => $log->getLoggedByPHID(),
      );
    }

    return $results;
  }

}
