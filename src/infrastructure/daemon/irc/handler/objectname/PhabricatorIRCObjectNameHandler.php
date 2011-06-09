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

  /**
   * Map of PHIDs to the last mention of them (as an epoch timestamp); prevents
   * us from spamming chat when a single object is discussed.
   */
  private $recentlyMentioned = array();

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

        $pattern =
          '@'.
          '(?<!/)(?:^|\b)'. // Negative lookbehind prevent matching "/D123".
          '(D|T)(\d+)'.
          '(?:\b|$)'.
          '@';

        $revision_ids = array();
        $task_ids = array();
        $commit_names = array();

        if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
          foreach ($matches as $match) {
            switch ($match[1]) {
              case 'D':
                $revision_ids[] = $match[2];
                break;
              case 'T':
                $task_ids[] = $match[2];
                break;
            }
          }
        }

        $pattern =
          '@'.
          '(?<!/)(?:^|\b)'.
          '(r[A-Z]+[0-9a-z]{1,40})'.
          '(?:\b|$)'.
          '@';
        if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
          foreach ($matches as $match) {
            $commit_names[] = $match[1];
          }
        }

        $output = array();

        if ($revision_ids) {
          $revisions = $this->getConduit()->callMethodSynchronous(
            'differential.find',
            array(
              'query' => 'revision-ids',
              'guids' => $revision_ids,
            ));
          foreach ($revisions as $revision) {
            $output[$revision['phid']] =
              'D'.$revision['id'].' '.$revision['name'].' - '.
              $revision['uri'];
          }
        }

        // TODO: Support tasks in Conduit.

        if ($commit_names) {
          $commits = $this->getConduit()->callMethodSynchronous(
            'diffusion.getcommits',
            array(
              'commits' => $commit_names,
            ));
          foreach ($commits as $commit) {
            if (isset($commit['error'])) {
              continue;
            }
            $output[$commit['commitPHID']] = $commit['uri'];
          }
        }

        foreach ($output as $phid => $description) {

          // Don't mention the same object more than once every 10 minutes, so
          // we avoid spamming the chat over and over again for discsussions of
          // a specific revision, for example.
          $quiet_until = idx($this->recentlyMentioned, $phid, 0) + (60 * 10);
          if (time() < $quiet_until) {
            continue;
          }
          $this->recentlyMentioned[$phid] = time();

          $this->write('PRIVMSG', "{$channel} :{$description}");
        }
        break;
    }
  }

}
