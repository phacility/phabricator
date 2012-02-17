<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Logs chatter.
 *
 * @group irc
 */
final class PhabricatorIRCLogHandler extends PhabricatorIRCHandler {

  private $futures = array();

  public function receiveMessage(PhabricatorIRCMessage $message) {

    switch ($message->getCommand()) {
      case 'PRIVMSG':
        $reply_to = $message->getReplyTo();
        if (!$reply_to) {
          break;
        }
        if (!$this->isChannelName($reply_to)) {
          // Don't log private messages, although maybe we should for debugging?
          break;
        }

        $logs = array(
          array(
            'channel' => $reply_to,
            'type'    => 'mesg',
            'epoch'   => time(),
            'author'  => $message->getSenderNickname(),
            'message' => $message->getMessageText(),
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
          if (preg_match($prompt, $message->getMessageText())) {
            $tell = true;
            break;
          }
        }

        if ($tell) {
          $response = $this->getURI(
            '/chatlog/channel/'.phutil_escape_uri($reply_to).'/');
          $this->write('PRIVMSG', "{$reply_to} :{$response}");
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
