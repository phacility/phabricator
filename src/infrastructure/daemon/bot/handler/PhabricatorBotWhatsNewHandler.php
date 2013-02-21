<?php

/**
 * Responds to "Whats new?" using the feed.
 *
 * @group irc
 */
final class PhabricatorBotWhatsNewHandler extends PhabricatorBotHandler {

  private $floodblock = 0;

  public function receiveMessage(PhabricatorBotMessage $message) {

    switch ($message->getCommand()) {
      case 'MESSAGE':
        $message_body = $message->getBody();

        $prompt = '~what( i|\')?s new\?~i';
        if (preg_match($prompt, $message_body)) {
          if (time() < $this->floodblock) {
            return;
          }
          $this->floodblock = time() + 60;

          $this->getLatest($message);
        }
        break;
    }
  }

  public function getLatest(PhabricatorBotMessage $message) {
    $latest = $this->getConduit()->callMethodSynchronous(
      'feed.query',
      array(
        'limit' => 5
      ));

    $phids = array();
    foreach ($latest as $action) {
      if (isset($action['data']['actor_phid'])) {
        $uid = $action['data']['actor_phid'];
      } else {
        $uid = $action['authorPHID'];
      }

      switch ($action['class']) {
        case 'PhabricatorFeedStoryDifferential':
          $phids[] = $action['data']['revision_phid'];
          break;
        case 'PhabricatorFeedStoryAudit':
          $phids[] = $action['data']['commitPHID'];
          break;

        case 'PhabricatorFeedStoryManiphest':
          $phids[] = $action['data']['taskPHID'];
          break;

        default:
          $phids[] = $uid;
          break;
      }
      array_push($phids, $uid);
    }

    $infs = $this->getConduit()->callMethodSynchronous(
      'phid.query',
      array(
        'phids'=>$phids
      ));

    $cphid = 0;
    foreach ($latest as $action) {
      if (isset($action['data']['actor_phid'])) {
        $uid = $action['data']['actor_phid'];
      } else {
        $uid = $action['authorPHID'];
      }
      switch ($action['class']) {
        case 'PhabricatorFeedStoryDifferential':
          $rinf = $infs[$action['data']['revision_phid']];
          break;

        case 'PhabricatorFeedStoryAudit':
          $rinf = $infs[$action['data']['commitPHID']];
          break;

        case 'PhabricatorFeedStoryManiphest':
          $rinf = $infs[$action['data']['taskPHID']];
          break;

        default:
          $rinf = array('name'=>$action['class']);
          break;
      }
      $uinf = $infs[$uid];

      $action = $this->getRhetoric($action['data']['action']);
      $user = $uinf['name'];
      $title = $rinf['fullName'];
      $uri = $rinf['uri'];
      $color = chr(3);
      $blue = $color.'12';
      $gray = $color.'15';
      $bold = chr(2);
      $reset = chr(15);
      // Disabling irc-specific styling, at least for now
      // $content = "{$bold}{$user}{$reset} {$gray}{$action} {$blue}{$bold}".
        // "{$title}{$reset} - {$gray}{$uri}{$reset}";
      $content = "{$user} {$action} {$title} - {$uri}";
      $this->replyTo($message, $content);
    }
    return;
  }

  public function getRhetoric($input) {
    switch ($input) {
      case 'comment':
      case 'none':
        return 'commented on';
        break;
      case 'update':
        return 'updated';
        break;
      case 'commit':
        return 'closed';
        break;
      case 'create':
        return 'created';
        break;
      case 'concern':
        return 'raised concern for';
        break;
      case 'abandon':
        return 'abandonned';
        break;
      case 'accept':
        return 'accepted';
        break;
      default:
        return $input;
        break;
    }
  }
}
