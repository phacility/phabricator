<?php

final class PhabricatorCampfireProtocolAdapter
extends PhabricatorBaseProtocolAdapter {

  private $readBuffers;
  private $authtoken;
  private $server;
  private $readHandles;
  private $multiHandle;
  private $active;
  private $inRooms = array();

  public function connect() {
    $this->server = idx($this->config, 'server');
    $this->authtoken = idx($this->config, 'authtoken');
    $ssl = idx($this->config, 'ssl', false);
    $rooms = idx($this->config, 'join');

    // First, join the room
    if (!$rooms) {
      throw new Exception("Not configured to join any rooms!");
    }

    $this->readBuffers = array();

    // Set up our long poll in a curl multi request so we can
    // continue running while it executes in the background
    $this->multiHandle = curl_multi_init();
    $this->readHandles = array();

    foreach ($rooms as $room_id) {
      $this->joinRoom($room_id);

      // Set up the curl stream for reading
      $url = ($ssl) ? "https://" : "http://";
      $url .= "streaming.campfirenow.com/room/{$room_id}/live.json";
      $this->readHandle[$url] = curl_init();
      curl_setopt($this->readHandle[$url], CURLOPT_URL, $url);
      curl_setopt($this->readHandle[$url], CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->readHandle[$url], CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt(
        $this->readHandle[$url],
        CURLOPT_USERPWD,
        $this->authtoken.':x');
      curl_setopt(
        $this->readHandle[$url],
        CURLOPT_HTTPHEADER,
        array("Content-type: application/json"));
      curl_setopt(
        $this->readHandle[$url],
        CURLOPT_WRITEFUNCTION,
        array($this, 'read'));
      curl_setopt($this->readHandle[$url], CURLOPT_BUFFERSIZE, 128);
      curl_setopt($this->readHandle[$url], CURLOPT_TIMEOUT, 0);

      curl_multi_add_handle($this->multiHandle, $this->readHandle[$url]);

      // Initialize read buffer
      $this->readBuffers[$url] = '';
    }

    $this->active = null;
    $this->blockingMultiExec();
  }

  // This is our callback for the background curl multi-request.
  // Puts the data read in on the readBuffer for processing.
  private function read($ch, $data) {
    $info = curl_getinfo($ch);
    $length = strlen($data);
    $this->readBuffers[$info['url']] .= $data;
    return $length;
  }

  private function blockingMultiExec() {
    do {
      $status = curl_multi_exec($this->multiHandle, $this->active);
    } while ($status == CURLM_CALL_MULTI_PERFORM);

    // Check for errors
    if ($status != CURLM_OK) {
      throw new Exception(
        "Phabricator Bot had a problem reading from campfire.");
    }
  }

  public function getNextMessages($poll_frequency) {
    $messages = array();

    if (!$this->active) {
      throw new Exception("Phabricator Bot stopped reading from campfire.");
    }

    // Prod our http request
    curl_multi_select($this->multiHandle, $poll_frequency);
    $this->blockingMultiExec();

    // Process anything waiting on the read buffer
    while ($m = $this->processReadBuffer()) {
      $messages[] = $m;
    }

    return $messages;
  }

  private function processReadBuffer() {
    foreach ($this->readBuffers as $url => &$buffer) {
      $until = strpos($buffer, "}\r");
      if ($until == false) {
        continue;
      }

      $message = substr($buffer, 0, $until + 1);
      $buffer = substr($buffer, $until + 2);

      $m_obj = json_decode($message, true);

      return id(new PhabricatorBotMessage())
        ->setCommand('MESSAGE')
        ->setTarget($m_obj['room_id'])
        ->setBody($m_obj['body']);
    }

    // If we're here, there's nothing to process
    return false;
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

  private function joinRoom($room_id) {
    $this->performPost("/room/{$room_id}/join.json");
    $this->inRooms[$room_id] = true;
  }

  private function leaveRoom($room_id) {
    $this->performPost("/room/{$room_id}/leave.json");
    unset($this->inRooms[$room_id]);
  }

  private function speak($message, $room_id) {
    $this->performPost(
      "/room/{$room_id}/speak.json",
      array(
        'message' => array(
          'type' => 'TextMessage',
          'body' => $message)));
  }

  private function performPost($endpoint, $data = Null) {
    $uri = new PhutilURI($this->server);
    $uri->setPath($endpoint);

    $payload = json_encode($data);

    list($output) = id(new HTTPSFuture($uri))
      ->setMethod('POST')
      ->addHeader('Content-Type', 'application/json')
      ->addHeader('Authorization', $this->getAuthorizationHeader())
      ->setData($payload)
      ->resolvex();

    $output = trim($output);
    if (strlen($output)) {
      return json_decode($output, true);
    }

    return true;
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

  private function getAuthorizationHeader() {
    return 'Basic '.base64_encode($this->authtoken.':x');
  }
}
