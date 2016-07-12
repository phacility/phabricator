<?php

/**
 * Responds to "Whats new?" with some recent feed content.
 */
final class PhabricatorBotWhatsNewHandler extends PhabricatorBotHandler {

  private $floodblock = 0;

  public function receiveMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
      case 'MESSAGE':
        $message_body = $message->getBody();
        $now = time();

        $prompt = '~what( i|\')?s new\?~i';
        if (preg_match($prompt, $message_body)) {
          if ($now < $this->floodblock) {
            return;
          }
          $this->floodblock = $now + 60;
          $this->reportNew($message);
        }
        break;
    }
  }

  public function reportNew(PhabricatorBotMessage $message) {
    $latest = $this->getConduit()->callMethodSynchronous(
      'feed.query',
      array(
        'limit' => 5,
        'view'  => 'text',
      ));

    foreach ($latest as $feed_item) {
      if (isset($feed_item['text'])) {
        $this->replyTo($message, html_entity_decode($feed_item['text']));
      }
    }
  }

}
