<?php

final class PhabricatorCampfireProtocolAdapter
  extends PhabricatorBotBaseStreamingProtocolAdapter {

  public function getServiceType() {
    return 'Campfire';
  }

  protected function buildStreamingUrl($channel) {
    $ssl = $this->getConfig('ssl');

    $url = ($ssl) ? 'https://' : 'http://';
    $url .= "streaming.campfirenow.com/room/{$channel}/live.json";

    return $url;
  }

  protected function processMessage(array $m_obj) {
      $command = null;
      switch ($m_obj['type']) {
        case 'TextMessage':
          $command = 'MESSAGE';
          break;
        case 'PasteMessage':
          $command = 'PASTE';
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
        ->setName($m_obj['user_id']);

      $target = id(new PhabricatorBotChannel())
        ->setName($m_obj['room_id']);

      return id(new PhabricatorBotMessage())
        ->setCommand($command)
        ->setSender($sender)
        ->setTarget($target)
        ->setBody($m_obj['body']);
  }

  public function writeMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
      case 'MESSAGE':
        $this->speak(
          $message->getBody(),
          $message->getTarget());
        break;
      case 'SOUND':
        $this->speak(
          $message->getBody(),
          $message->getTarget(),
          'SoundMessage');
        break;
      case 'PASTE':
        $this->speak(
          $message->getBody(),
          $message->getTarget(),
          'PasteMessage');
        break;
    }
  }

  protected function joinRoom($room_id) {
    $this->performPost("/room/{$room_id}/join.json");
    $this->inRooms[$room_id] = true;
  }

  private function leaveRoom($room_id) {
    $this->performPost("/room/{$room_id}/leave.json");
    unset($this->inRooms[$room_id]);
  }

  private function speak(
    $message,
    PhabricatorBotTarget $channel,
    $type = 'TextMessage') {

    $room_id = $channel->getName();

    $this->performPost(
      "/room/{$room_id}/speak.json",
      array(
        'message' => array(
          'type' => $type,
          'body' => $message,
        ),
      ));
  }

  public function __destruct() {
    foreach ($this->inRooms as $room_id => $ignored) {
      $this->leaveRoom($room_id);
    }

    if ($this->readHandles) {
      foreach ($this->readHandles as $read_handle) {
        curl_multi_remove_handle($this->multiHandle, $read_handle);
        curl_close($read_handle);
      }
    }

    curl_multi_close($this->multiHandle);
  }
}
