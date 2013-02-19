<?php

final class PhabricatorBotFlowdockProtocolAdapter
  extends PhabricatorBotBaseStreamingProtocolAdapter {

  public function getServiceType() {
    return 'Flowdock';
  }

  protected function buildStreamingUrl($channel) {
    $organization = $this->getConfig('organization');
    $ssl = $this->getConfig('ssl');

    $url = ($ssl) ? "https://" : "http://";
    $url .= "stream.flowdock.com/flows/{$organization}/{$channel}";

    return $url;
  }

  protected function processMessage($m_obj) {
      $command = null;
      switch ($m_obj['event']) {
        case 'message':
          $command = 'MESSAGE';
          break;
        default:
          // For now, ignore anything which we don't otherwise know about.
          break;
      }

      if ($command === null) {
        return false;
      }

      // TODO: These should be usernames, not user IDs.
      $sender = id(new PhabricatorBotUser())
        ->setName($m_obj['user']);

      $target = id(new PhabricatorBotChannel())
        ->setName($m_obj['flow']);

      return id(new PhabricatorBotMessage())
        ->setCommand($command)
        ->setSender($sender)
        ->setTarget($target)
        ->setBody($m_obj['content']);
  }

  public function writeMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
      case 'MESSAGE':
        $this->speak(
          $message->getBody(),
          $message->getTarget());
        break;
    }
  }

  private function speak(
    $body,
    PhabricatorBotTarget $flow) {

    list($organization, $room_id) = explode(":", $flow->getName());

    $this->performPost(
      "/flows/{$organization}/{$room_id}/messages",
      array(
        'event' => 'message',
        'content' => $body));
  }

  public function __destruct() {
    if ($this->readHandles) {
      foreach ($this->readHandles as $read_handle) {
        curl_multi_remove_handle($this->multiHandle, $read_handle);
        curl_close($read_handle);
      }
    }

    curl_multi_close($this->multiHandle);
  }
}
