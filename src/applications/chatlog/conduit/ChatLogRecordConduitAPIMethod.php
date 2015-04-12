<?php

final class ChatLogRecordConduitAPIMethod extends ChatLogConduitAPIMethod {

  public function getAPIMethodName() {
    return 'chatlog.record';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return 'Record chatter.';
  }

  protected function defineParamTypes() {
    return array(
      'logs' => 'required list<dict>',
    );
  }

  protected function defineReturnType() {
    return 'list<id>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $logs = $request->getValue('logs');
    if (!is_array($logs)) {
      $logs = array();
    }

    $template = new PhabricatorChatLogEvent();
    $template->setLoggedByPHID($request->getUser()->getPHID());

    $objs = array();
    foreach ($logs as $log) {
      $channel_name = idx($log, 'channel');
      $service_name = idx($log, 'serviceName');
      $service_type = idx($log, 'serviceType');

      $channel = id(new PhabricatorChatLogChannel())->loadOneWhere(
        'channelName = %s AND serviceName = %s AND serviceType = %s',
        $channel_name,
        $service_name,
        $service_type);

      if (!$channel) {
        $channel = id(new PhabricatorChatLogChannel())
          ->setChannelName($channel_name)
          ->setserviceName($service_name)
          ->setServiceType($service_type)
          ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
          ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
          ->save();
      }

      $obj = clone $template;
      $obj->setChannelID($channel->getID());
      $obj->setType(idx($log, 'type'));
      $obj->setAuthor(idx($log, 'author'));
      $obj->setEpoch(idx($log, 'epoch'));
      $obj->setMessage(idx($log, 'message'));
      $obj->save();

      $objs[] = $obj;
    }

    return array_values(mpull($objs, 'getID'));
  }

}
