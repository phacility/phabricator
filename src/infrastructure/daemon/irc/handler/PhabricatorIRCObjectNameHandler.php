<?php

/**
 * Looks for Dxxxx, Txxxx and links to them.
 *
 * @group irc
 */
final class PhabricatorIRCObjectNameHandler extends PhabricatorIRCHandler {

  /**
   * Map of PHIDs to the last mention of them (as an epoch timestamp); prevents
   * us from spamming chat when a single object is discussed.
   */
  private $recentlyMentioned = array();

  public function receiveMessage(PhabricatorIRCMessage $message) {

    switch ($message->getCommand()) {
      case 'PRIVMSG':
        $reply_to = $message->getReplyTo();
        if (!$reply_to) {
          break;
        }

        $this->handleSymbols($message);

        $message = $message->getMessageText();
        $matches = null;

        $pattern =
          '@'.
          '(?<!/)(?:^|\b)'. // Negative lookbehind prevent matching "/D123".
          '(D|T|P|V|F)(\d+)'.
          '(?:\b|$)'.
          '@';
        $pattern_override = '/(^[^\s]+)[,:] [DTPVF]\d+/';

        $revision_ids = array();
        $task_ids = array();
        $paste_ids = array();
        $commit_names = array();
        $vote_ids = array();
        $file_ids = array();
        $matches_override = array();

        if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
          if (preg_match($pattern_override, $message, $matches_override)) {
            $reply_to = $matches_override[1];
          }
          foreach ($matches as $match) {
            switch ($match[1]) {
              case 'D':
                $revision_ids[] = $match[2];
                break;
              case 'T':
                $task_ids[] = $match[2];
                break;
              case 'P':
                $paste_ids[] = $match[2];
                break;
              case 'V':
                 $vote_ids[] = $match[2];
                 break;
              case 'F':
                 $file_ids[] = $match[2];
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
            'differential.query',
            array(
              'query' => 'revision-ids',
              'ids'   => $revision_ids,
            ));
          $revisions = array_select_keys(
            ipull($revisions, null, 'id'),
            $revision_ids
          );
          foreach ($revisions as $revision) {
            $output[$revision['phid']] =
              'D'.$revision['id'].' '.$revision['title'].' - '.
              $revision['uri'];
          }
        }

        if ($task_ids) {
          foreach ($task_ids as $task_id) {
            if ($task_id == 1000) {
              $output[1000] = 'T1000: A nanomorph mimetic poly-alloy'
               .'(liquid metal) assassin controlled by Skynet: '
               .'http://en.wikipedia.org/wiki/T-1000';
              continue;
            }
            $task = $this->getConduit()->callMethodSynchronous(
              'maniphest.info',
              array(
                'task_id' => $task_id,
              ));
            $output[$task['phid']] = 'T'.$task['id'].': '.$task['title'].
              ' (Priority: '.$task['priority'].') - '.$task['uri'];
          }
        }

       if ($vote_ids) {
         foreach ($vote_ids as $vote_id) {
           $vote = $this->getConduit()->callMethodSynchronous(
             'slowvote.info',
             array(
               'poll_id' => $vote_id,
           ));
           $output[$vote['phid']] = 'V'.$vote['id'].': '.$vote['question'].
              ' Come Vote '.$vote['uri'];
         }
       }

       if ($file_ids) {
         foreach ($file_ids as $file_id) {
           $file = $this->getConduit()->callMethodSynchronous(
             'file.info',
             array(
               'id' => $file_id,
           ));
           $output[$file['phid']] = $file['objectName'].": ".$file['uri']." - ".
              $file['name'];
         }
       }

        if ($paste_ids) {
          foreach ($paste_ids as $paste_id) {
            $paste = $this->getConduit()->callMethodSynchronous(
              'paste.info',
              array(
                'paste_id' => $paste_id,
              ));
            // Eventually I'd like to show the username of the paster as well,
            // however that will need something like a user.username_from_phid
            // since we (ideally) want to keep the bot to Conduit calls...and
            // not call to Phabricator-specific stuff (like actually loading
            // the User object and fetching his/her username.)
            $output[$paste['phid']] = 'P'.$paste['id'].': '.$paste['uri'].' - '.
              $paste['title'];

            if ($paste['language']) {
              $output[$paste['phid']] .= ' ('.$paste['language'].')';
            }
          }
        }

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

          // Don't mention the same object more than once every 10 minutes
          // in public channels, so we avoid spamming the chat over and over
          // again for discsussions of a specific revision, for example. In
          // direct-to-bot chat, respond to every object reference.

          if ($this->isChannelName($reply_to)) {
            if (empty($this->recentlyMentioned[$reply_to])) {
              $this->recentlyMentioned[$reply_to] = array();
            }

            $quiet_until = idx(
              $this->recentlyMentioned[$reply_to],
              $phid,
              0) + (60 * 10);

            if (time() < $quiet_until) {
              // Remain quiet on this channel.
              continue;
            }

            $this->recentlyMentioned[$reply_to][$phid] = time();
          }

          $this->write('PRIVMSG', "{$reply_to} :{$description}");
        }
        break;
    }
  }

  private function handleSymbols(PhabricatorIRCMessage $message) {
    $reply_to = $message->getReplyTo();
    $text = $message->getMessageText();

    $matches = null;
    if (!preg_match('/where(?: in the world)? is (\S+?)\?/i',
        $text, $matches)) {
      return;
    }

    $symbol = $matches[1];
    $results = $this->getConduit()->callMethodSynchronous(
      'diffusion.findsymbols',
      array(
        'name' => $symbol,
      ));

    $default_uri = $this->getURI('/diffusion/symbol/'.$symbol.'/');

    if (count($results) > 1) {
      $response = "Multiple symbols named '{$symbol}': {$default_uri}";
    } else if (count($results) == 1) {
      $result = head($results);
      $response =
        $result['type'].' '.
        $result['name'].' '.
        '('.$result['language'].'): '.
        nonempty($result['uri'], $default_uri);
    } else {
      $response = "No symbol '{$symbol}' found anywhere.";
    }

    $this->write('PRIVMSG', "{$reply_to} :{$response}");
  }

}
