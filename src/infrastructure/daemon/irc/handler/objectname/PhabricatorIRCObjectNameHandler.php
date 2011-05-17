<?php

/*
 * Copyright 2011 Facebook, Inc.
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
 * Looks for Dxxxx, Txxxx and links to them.
 *
 * @group irc
 */
class PhabricatorIRCObjectNameHandler extends PhabricatorIRCHandler {

  public function receiveMessage(PhabricatorIRCMessage $message) {

    switch ($message->getCommand()) {
      case 'PRIVMSG':
        $channel = $message->getChannel();
        if (!$channel) {
          break;
        }

        $message = $message->getMessageText();
        $matches = null;
        $phids = array();
        if (preg_match_all('/(?:^|\b)D(\d+)(?:\b|$)/', $message, $matches)) {
          if ($matches[1]) {
            $revisions = $this->getConduit()->callMethodSynchronous(
              'differential.find',
              array(
                'query' => 'revision-ids',
                'guids' => $matches[1],
              ));

            // TODO: This is utter hacks until phid.find or similar lands.
            foreach ($revisions as $revision) {
              $phids[$revision['phid']] =
                'D'.$revision['id'].' '.$revision['name'].' - '.
                PhabricatorEnv::getProductionURI('/D'.$revision['id']);
            }
          }
        }
        foreach ($phids as $phid => $description) {
          $this->write('PRIVMSG', "{$channel} :{$description}");
        }
        break;
    }
  }

}
