<?php

final class ChatLogQueryConduitAPIMethod extends ChatLogConduitAPIMethod {

  public function getAPIMethodName() {
    return 'chatlog.query';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return 'Retrieve chatter.';
  }

  protected function defineParamTypes() {
    return array(
      'channels' => 'optional list<string>',
      'limit'    => 'optional int (default = 100)',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new PhabricatorChatLogQuery();

    $channel_ids = $request->getValue('channelIDs');
    if ($channel_ids) {
      $query->withChannelIDs($channel_ids);
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
        'channelID'       => $log->getChannelID(),
        'epoch'           => $log->getEpoch(),
        'author'          => $log->getAuthor(),
        'type'            => $log->getType(),
        'message'         => $log->getMessage(),
        'loggedByPHID'    => $log->getLoggedByPHID(),
      );
    }

    return $results;
  }

}
