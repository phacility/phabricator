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
 * Implements the base IRC protocol so servers don't kick you off.
 *
 * @group irc
 */
final class PhabricatorIRCProtocolHandler extends PhabricatorIRCHandler {

  public function receiveMessage(PhabricatorIRCMessage $message) {
    switch ($message->getCommand()) {
      case '422': // Error - no MOTD
      case '376': // End of MOTD
        $join = $this->getConfig('join');
        if (!$join) {
          throw new Exception("Not configured to join any channels!");
        }
        foreach ($join as $channel) {
          $this->write('JOIN', $channel);
        }
        break;
      case 'PING':
        $this->write('PONG', $message->getRawData());
        break;
    }
  }

}
