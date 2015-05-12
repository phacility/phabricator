<?php

final class PhabricatorBotFlowdockProtocolAdapter
  extends PhabricatorBotBaseStreamingProtocolAdapter {

  public function getServiceType() {
    return 'Flowdock';
  }

  protected function buildStreamingUrl($channel) {
    $organization = $this->getConfig('flowdock.organization');
    if (empty($organization)) {
      $this->getConfig('organization');
    }
    if (empty($organization)) {
      throw new Exception(
        '"flowdock.organization" configuration variable not set');
    }


    $ssl = $this->getConfig('ssl');

    $url = ($ssl) ? 'https://' : 'http://';
    $url .= "{$this->authtoken}@stream.flowdock.com";
    $url .= "/flows/{$organization}/{$channel}";
    return $url;
  }

  protected function processMessage(array $m_obj) {
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
      // The $flow->getName() returns the flow's UUID,
      // as such, the Flowdock API does not require the organization
      // to be specified in the URI
      $this->performPost(
        '/messages',
        array(
          'flow' => $flow->getName(),
          'event' => 'message',
          'content' => $body,
        ));
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
