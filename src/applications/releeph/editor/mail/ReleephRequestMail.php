<?php

/**
 * Build an email that renders a group of events with and appends some standard
 * Releeph things (a URI for this request, and this branch).
 *
 * Also includes some helper stuff for adding groups of people to the To: and
 * Cc: headers.
 */
final class ReleephRequestMail {

  const ENT_REQUESTOR           = 'requestor';
  const ENT_DIFF                = 'diff';
  const ENT_ALL_PUSHERS         = 'pushers';
  const ENT_ACTORS              = 'actors';
  const ENT_INTERESTED_PUSHERS  = 'interested-pushers';

  private $sender;
  private $tos = array();
  private $ccs = array();
  private $events;
  private $releephRequest;
  private $releephProject;

  public function setReleephRequest(ReleephRequest $rq) {
    $this->releephRequest = $rq;
    return $this;
  }

  public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  public function setEvents(array $events) {
    assert_instances_of($events, 'ReleephRequestEvent');
    $this->events = $events;
    return $this;
  }

  public function setSenderAndRecipientPHID($sender_phid) {
    $this->sender = $sender_phid;
    $this->tos[] = $sender_phid;
    return $this;
  }

  public function addTos($entity) {
    $this->tos = array_merge(
      $this->tos,
      $this->getEntityPHIDs($entity));
    return $this;
  }

  public function addCcs($entity) {
    $this->ccs = array_merge(
      $this->tos,
      $this->getEntityPHIDs($entity));
    return $this;
  }

  public function send() {
    $this->buildMail()->save();
  }

  public function buildMail() {
    return id(new PhabricatorMetaMTAMail())
      ->setSubject($this->renderSubject())
      ->setBody($this->buildBody()->render())
      ->setFrom($this->sender)
      ->addTos($this->tos)
      ->addCCs($this->ccs);
  }

  private function getEntityPHIDs($entity) {
    $phids = array();
    switch ($entity) {
      // The requestor
      case self::ENT_REQUESTOR:
        $phids[] = $this->releephRequest->getRequestUserPHID();
        break;

      // People on the original diff
      case self::ENT_DIFF:
        $commit = $this->releephRequest->loadPhabricatorRepositoryCommit();
        $commit_data = $commit->loadCommitData();
        if ($commit_data) {
          $phids[] = $commit_data->getCommitDetail('reviewerPHID');
          $phids[] = $commit_data->getCommitDetail('authorPHID');
        }
        break;

      // All pushers for this project
      case self::ENT_ALL_PUSHERS:
        $phids = array_merge(
          $phids,
          $this->releephProject->getPushers());
        break;

      // Pushers who have explicitly wanted or passed on this request
      case self::ENT_INTERESTED_PUSHERS:
        $all_pushers = $this->releephProject->getPushers();
        $intents = $this->releephRequest->getUserIntents();
        foreach ($all_pushers as $pusher) {
          if (idx($intents, $pusher)) {
            $phids[] = $pusher;
          }
        }
        break;

      // Anyone who created our list of events
      case self::ENT_ACTORS:
        $phids = array_merge(
          $phids,
          mpull($this->events, 'getActorPHID'));
        break;

      default:
        throw new Exception(
          "Unknown entity type {$entity}!");
        break;
    }

    return array_filter($phids);
  }

  private function buildBody() {
    $body = new PhabricatorMetaMTAMailBody();
    $rq = $this->releephRequest;

    // Events and comments
    $phids = array(
      $rq->getPHID(),
    );
    foreach ($this->events as $event) {
      $phids = array_merge($phids, $event->extractPHIDs());
    }
    $handles = id(new PhabricatorObjectHandleData($phids))
      // By the time we're generating email, we can assume that whichever
      // entitties are receving the email are authorized to see the loaded
      // handles!
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->loadHandles();

    $raw_events = id(new ReleephRequestEventListView())
      ->setUser(PhabricatorUser::getOmnipotentUser())
      ->setHandles($handles)
      ->setEvents($this->events)
      ->renderForEmail();

    $body->addRawSection($raw_events);

    $project = $rq->loadReleephProject();
    $branch = $rq->loadReleephBranch();

    /**
     * If any of the events we are emailing about were TYPE_PICK_STATUS where
     * the newPickStatus was a pick failure (and/or a revert failure?), include
     * pick failure instructions.
     */
    $pick_failure_events = array();
    foreach ($this->events as $event) {
      if ($event->getType() == ReleephRequestEvent::TYPE_PICK_STATUS &&
          $event->getDetail('newPickStatus') == ReleephRequest::PICK_FAILED) {

        $pick_failure_events[] = $event;
      }
    }

    if ($pick_failure_events) {
      $instructions = $project->getDetail('pick_failure_instructions');
      if ($instructions) {
        $body->addTextSection('PICK FAILURE INSTRUCTIONS', $instructions);
      }
    }

    // Common stuff at the end
    $body->addTextSection(
      'RELEEPH REQUEST',
      $handles[$rq->getPHID()]->getFullName()."\n".
      PhabricatorEnv::getProductionURI('/RQ'.$rq->getID()));

    $project_and_branch = sprintf(
      '%s - %s',
      $project->getName(),
      $branch->getDisplayNameWithDetail());

    $body->addTextSection(
      'RELEEPH BRANCH',
      $project_and_branch."\n".
      $branch->getURI());

    // But verbose stuff at the *very* end!
    foreach ($pick_failure_events as $event) {
      $failure_details = $event->getDetail('commitDetails');
      if ($failure_details) {
        $body->addRawSection('PICK FAILURE DETAILS');
        foreach ($failure_details as $heading => $data) {
          $body->addTextSection($heading, $data);
        }
      }
    }

    return $body;
  }

  private function renderSubject() {
    $rq = $this->releephRequest;
    $id = $rq->getID();
    $summary = $rq->getSummaryForDisplay();
    return "RQ{$id}: {$summary}";
  }

}
