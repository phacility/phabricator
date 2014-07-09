<?php

/**
 * Logs chatter.
 */
final class PhabricatorBotLogHandler extends PhabricatorBotHandler {

  private $futures = array();

  public function receiveMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
      case 'MESSAGE':
        $target = $message->getTarget();
        if (!$target->isPublic()) {
          // Don't log private messages, although maybe we should for debugging?
          break;
        }

        $target_name = $target->getName();

        $logs = array(
          array(
            'channel' => $target_name,
            'type'    => 'mesg',
            'epoch'   => time(),
            'author'  => $message->getSender()->getName(),
            'message' => $message->getBody(),
            'serviceName' => $this->getServiceName(),
            'serviceType' => $this->getServiceType(),
          ),
        );

        $this->futures[] = $this->getConduit()->callMethod(
          'chatlog.record',
          array(
            'logs' => $logs,
          ));

        $prompts = array(
          '/where is the (chat)?log\?/i',
          '/where am i\?/i',
          '/what year is (this|it)\?/i',
        );

        $tell = false;
        foreach ($prompts as $prompt) {
          if (preg_match($prompt, $message->getBody())) {
            $tell = true;
            break;
          }
        }

        if ($tell) {
          $response = $this->getURI(
            '/chatlog/channel/'.phutil_escape_uri($target_name).'/');

          $this->replyTo($message, $response);
        }

        break;
    }
  }

  public function runBackgroundTasks() {
    foreach ($this->futures as $key => $future) {
      try {
        if ($future->isReady()) {
          unset($this->futures[$key]);
        }
      } catch (Exception $ex) {
        unset($this->futures[$key]);
        phlog($ex);
      }
    }
  }

}
