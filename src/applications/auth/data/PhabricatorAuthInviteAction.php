<?php

final class PhabricatorAuthInviteAction extends Phobject {

  private $rawInput;
  private $emailAddress;
  private $userPHID;
  private $issues = array();
  private $action;

  const ACTION_SEND       = 'invite.send';
  const ACTION_ERROR      = 'invite.error';
  const ACTION_IGNORE     = 'invite.ignore';

  const ISSUE_PARSE       = 'invite.parse';
  const ISSUE_DUPLICATE   = 'invite.duplicate';
  const ISSUE_UNVERIFIED  = 'invite.unverified';
  const ISSUE_VERIFIED    = 'invite.verified';
  const ISSUE_INVITED     = 'invite.invited';
  const ISSUE_ACCEPTED    = 'invite.accepted';

  public function getRawInput() {
    return $this->rawInput;
  }

  public function getEmailAddress() {
    return $this->emailAddress;
  }

  public function getUserPHID() {
    return $this->userPHID;
  }

  public function getIssues() {
    return $this->issues;
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function willSend() {
    return ($this->action == self::ACTION_SEND);
  }

  public function getShortNameForIssue($issue) {
    $map = array(
      self::ISSUE_PARSE => pht('Not a Valid Email Address'),
      self::ISSUE_DUPLICATE => pht('Address Duplicated in Input'),
      self::ISSUE_UNVERIFIED => pht('Unverified User Email'),
      self::ISSUE_VERIFIED => pht('Verified User Email'),
      self::ISSUE_INVITED => pht('Previously Invited'),
      self::ISSUE_ACCEPTED => pht('Already Accepted Invite'),
    );

    return idx($map, $issue);
  }

  public function getShortNameForAction($action) {
    $map = array(
      self::ACTION_SEND => pht('Will Send Invite'),
      self::ACTION_ERROR => pht('Address Error'),
      self::ACTION_IGNORE => pht('Will Ignore Address'),
    );

    return idx($map, $action);
  }

  public function getIconForAction($action) {
    switch ($action) {
      case self::ACTION_SEND:
        $icon = 'fa-envelope-o';
        $color = 'green';
        break;
      case self::ACTION_IGNORE:
        $icon = 'fa-ban';
        $color = 'grey';
        break;
      case self::ACTION_ERROR:
        $icon = 'fa-exclamation-triangle';
        $color = 'red';
        break;
    }

    return id(new PHUIIconView())
      ->setIcon("{$icon} {$color}");
  }

  public static function newActionListFromAddresses(
    PhabricatorUser $viewer,
    array $addresses) {

    $results = array();
    foreach ($addresses as $address) {
      $result = new PhabricatorAuthInviteAction();
      $result->rawInput = $address;

      $email = new PhutilEmailAddress($address);
      $result->emailAddress = phutil_utf8_strtolower($email->getAddress());

      if (!preg_match('/^\S+@\S+\.\S+\z/', $result->emailAddress)) {
        $result->issues[] = self::ISSUE_PARSE;
      }

      $results[] = $result;
    }

    // Identify duplicates.
    $address_groups = mgroup($results, 'getEmailAddress');
    foreach ($address_groups as $address => $group) {
      if (count($group) > 1) {
        foreach ($group as $action) {
          $action->issues[] = self::ISSUE_DUPLICATE;
        }
      }
    }

    // Identify addresses which are already in the system.
    $addresses = mpull($results, 'getEmailAddress');
    $email_objects = id(new PhabricatorUserEmail())->loadAllWhere(
      'address IN (%Ls)',
      $addresses);

    $email_map = array();
    foreach ($email_objects as $email_object) {
      $address_key = phutil_utf8_strtolower($email_object->getAddress());
      $email_map[$address_key] = $email_object;
    }

    // Identify outstanding invites.
    $invites = id(new PhabricatorAuthInviteQuery())
      ->setViewer($viewer)
      ->withEmailAddresses($addresses)
      ->execute();
    $invite_map = mpull($invites, null, 'getEmailAddress');

    foreach ($results as $action) {
      $email = idx($email_map, $action->getEmailAddress());
      if ($email) {
        if ($email->getUserPHID()) {
          $action->userPHID = $email->getUserPHID();
          if ($email->getIsVerified()) {
            $action->issues[] = self::ISSUE_VERIFIED;
          } else {
            $action->issues[] = self::ISSUE_UNVERIFIED;
          }
        }
      }

      $invite = idx($invite_map, $action->getEmailAddress());
      if ($invite) {
        if ($invite->getAcceptedByPHID()) {
          $action->issues[] = self::ISSUE_ACCEPTED;
          if (!$action->userPHID) {
            // This could be different from the user who is currently attached
            // to the email address if the address was removed or added to a
            // different account later. Only show it if the address was
            // removed, since the current status is more up-to-date otherwise.
            $action->userPHID = $invite->getAcceptedByPHID();
          }
        } else {
          $action->issues[] = self::ISSUE_INVITED;
        }
      }
    }

    foreach ($results as $result) {
      foreach ($result->getIssues() as $issue) {
        switch ($issue) {
          case self::ISSUE_PARSE:
            $result->action = self::ACTION_ERROR;
            break;
          case self::ISSUE_ACCEPTED:
          case self::ISSUE_VERIFIED:
            $result->action = self::ACTION_IGNORE;
            break;
        }
      }
      if (!$result->action) {
        $result->action = self::ACTION_SEND;
      }
    }

    return $results;
  }

  public function sendInvite(PhabricatorUser $actor, $template) {
    if (!$this->willSend()) {
      throw new Exception(pht('Invite action is not a send action!'));
    }

    if (!preg_match('/{\$INVITE_URI}/', $template)) {
      throw new Exception(pht('Invite template does not include invite URI!'));
    }

    PhabricatorWorker::scheduleTask(
      'PhabricatorAuthInviteWorker',
      array(
        'address' => $this->getEmailAddress(),
        'template' => $template,
        'authorPHID' => $actor->getPHID(),
      ));
  }

}
