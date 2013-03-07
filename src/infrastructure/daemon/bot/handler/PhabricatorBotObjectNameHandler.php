<?php

/**
 * Looks for Dxxxx, Txxxx and links to them.
 *
 * @group irc
 */
final class PhabricatorBotObjectNameHandler extends PhabricatorBotHandler {

  /**
   * Map of PHIDs to the last mention of them (as an epoch timestamp); prevents
   * us from spamming chat when a single object is discussed.
   */
  private $recentlyMentioned = array();

  public function receiveMessage(PhabricatorBotMessage $original_message) {

    switch ($original_message->getCommand()) {
    case 'MESSAGE':
      $message = $original_message->getBody();
      $matches = null;

      $paste_ids = array();
      $commit_names = array();
      $vote_ids = array();
      $file_ids = array();
      $object_names = array();
      $output = array();

      $pattern =
        '@'.
        '(?<!/)(?:^|\b)'.
        '(R2D2)'.
        '(?:\b|$)'.
        '@';

      if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          switch ($match[1]) {
            case 'R2D2':
              $output[$match[1]] = pht('beep hoop bop');
              break;
          }
        }
      }

      $pattern =
        '@'.
        '(?<!/)(?:^|\b)'. // Negative lookbehind prevent matching "/D123".
        '([A-Z])(\d+)'.
        '(?:\b|$)'.
        '@';

      if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          switch ($match[1]) {
            case 'P':
              $paste_ids[] = $match[2];
              break;
            case 'V':
              $vote_ids[] = $match[2];
              break;
            case 'F':
              $file_ids[] = $match[2];
              break;
            default:
              $name = $match[1].$match[2];
              switch ($name) {
                case 'T1000':
                  $output[$name] = pht(
                    'T1000: A mimetic poly-alloy assassin controlled by '.
                    'Skynet');
                  break;
                default:
                  $object_names[] = $name;
                  break;
              }
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

      if ($object_names) {
        $objects = $this->getConduit()->callMethodSynchronous(
          'phid.lookup',
          array(
            'names' => $object_names,
          ));
        foreach ($objects as $object) {
          $output[$object['phid']] = $object['fullName'].' - '.$object['uri'];
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
        // again for discsussions of a specific revision, for example.

        $target_name = $original_message->getTarget()->getName();
        if (empty($this->recentlyMentioned[$target_name])) {
          $this->recentlyMentioned[$target_name] = array();
        }

        $quiet_until = idx(
          $this->recentlyMentioned[$target_name],
          $phid,
          0) + (60 * 10);

        if (time() < $quiet_until) {
          // Remain quiet on this channel.
          continue;
        }

        $this->recentlyMentioned[$target_name][$phid] = time();
        $this->replyTo($original_message, $description);
      }
      break;
    }
  }

}
