<?php

/**
 * Provide methods for the common ways of creating and mutating a
 * ReleephRequest, sending email when something interesting happens.
 *
 * This class generates ReleephRequestEvents, and each type of event
 * (ReleephRequestEvent::TYPE_*) corresponds to one of the editor methods.
 *
 * The editor methods (except for create() use newEvent() and commit() to save
 * some code duplication.
 */
final class ReleephRequestEditor extends PhabricatorEditor {

  private $releephRequest;
  private $event;
  private $silentUpdate;

  public function __construct(ReleephRequest $rq) {
    $this->releephRequest = $rq;
  }

  public function setSilentUpdate($silent) {
    $this->silentUpdate = $silent;
    return $this;
  }


/* -(  ReleephRequest edit methods  )---------------------------------------- */

  /**
   * Request a PhabricatorRepositoryCommit to be committed to the given
   * ReleephBranch.
   */
  public function create(PhabricatorRepositoryCommit $commit,
                         ReleephBranch $branch) {

    // We can't use newEvent() / commit() abstractions, so do what those
    // helpers do manually.
    $requestor = $this->requireActor();

    $rq = $this->releephRequest;
    $rq->openTransaction();

    $rq
      ->setBranchID($branch->getID())
      ->setRequestCommitIdentifier($commit->getCommitIdentifier())
      ->setRequestCommitPHID($commit->getPHID())
      ->setRequestCommitOrdinal($commit->getID())
      ->setInBranch(0)
      ->setRequestUserPHID($requestor->getPHID())
      ->setUserIntent($requestor, ReleephRequest::INTENT_WANT)
      ->save();

    $event = id(new ReleephRequestEvent())
      ->setType(ReleephRequestEvent::TYPE_CREATE)
      ->setActorPHID($requestor->getPHID())
      ->setStatusBefore(null)
      ->setStatusAfter($rq->getStatus())
      ->setReleephRequestID($rq->getID())
      ->setDetail('commitPHID', $commit->getPHID())
      ->save();

    $rq->saveTransaction();

    // Mail
    if (!$this->silentUpdate) {
      $project = $this->releephRequest->loadReleephProject();
      $mail = id(new ReleephRequestMail())
        ->setReleephRequest($this->releephRequest)
        ->setReleephProject($project)
        ->setEvents(array($event))
        ->setSenderAndRecipientPHID($requestor->getPHID())
        ->addTos(ReleephRequestMail::ENT_ALL_PUSHERS)
        ->addCCs(ReleephRequestMail::ENT_REQUESTOR)
        ->send();
    }
  }

  /**
   * Record whether the PhabricatorUser wants or passes on this request.
   */
  public function changeUserIntent(PhabricatorUser $user, $intent) {
    $project = $this->releephRequest->loadReleephProject();
    $is_pusher = $project->isPusher($user);

    $event = $this->newEvent()
      ->setType(ReleephRequestEvent::TYPE_USER_INTENT)
      ->setDetail('userPHID', $user->getPHID())
      ->setDetail('wasPusher', $is_pusher)
      ->setDetail('newIntent', $intent);

    $this->releephRequest
      ->setUserIntent($user, $intent);

    $this->commit();

    // Mail if this is 'interesting'
    if (!$this->silentUpdate &&
        $event->getStatusBefore() != $event->getStatusAfter()) {

      $project = $this->releephRequest->loadReleephProject();
      $mail = id(new ReleephRequestMail())
        ->setReleephRequest($this->releephRequest)
        ->setReleephProject($project)
        ->setEvents(array($event))
        ->setSenderAndRecipientPHID($this->requireActor()->getPHID())
        ->addTos(ReleephRequestMail::ENT_REQUESTOR)
        ->addCCs(ReleephRequestMail::ENT_INTERESTED_PUSHERS)
        ->send();
    }
  }

  /**
   * Record the results of someone trying to pick or revert a request in their
   * local repository, to give advance warning that something doesn't pick or
   * revert cleanly.
   */
  public function changePickStatus($pick_status, $dry_run, $details) {
    $event = $this->newEvent()
      ->setType(ReleephRequestEvent::TYPE_PICK_STATUS)
      ->setDetail('newPickStatus', $pick_status)
      ->setDetail('commitDetails', $details);
    $this->releephRequest->setPickStatus($pick_status);
    $this->commit();

    // Failures should generate an email
    if (!$this->silentUpdate &&
        !$dry_run &&
        ($pick_status == ReleephRequest::PICK_FAILED ||
         $pick_status == ReleephRequest::REVERT_FAILED)) {

      $project = $this->releephRequest->loadReleephProject();
      $mail = id(new ReleephRequestMail())
        ->setReleephRequest($this->releephRequest)
        ->setReleephProject($project)
        ->setEvents(array($event))
        ->setSenderAndRecipientPHID($this->requireActor()->getPHID())
        ->addTos(ReleephRequestMail::ENT_REQUESTOR)
        ->addCCs(ReleephRequestMail::ENT_ACTORS)
        ->addCCs(ReleephRequestMail::ENT_INTERESTED_PUSHERS)
        ->send();
    }
  }

  /**
   * Record that a request was committed locally, and is about to be pushed to
   * the remote repository.
   *
   * This lets us mark a ReleephRequest as being in a branch in real time so
   * that no one else tries to pick it.
   *
   * When the daemons discover this commit in the repository with
   * DifferentialReleephRequestFieldSpecification, we'll be able to recrod the
   * commit's PHID as well.  That process is slow though, and
   * we don't want to wait a whole minute before marking something as cleanly
   * picked or reverted.
   */
  public function recordSuccessfulCommit($action, $new_commit_id) {
    $table = $this->releephRequest;
    $table->openTransaction();

    $actor = $this->requireActor();

    $event = id(new ReleephRequestEvent())
      ->setReleephRequestID($this->releephRequest->getID())
      ->setActorPHID($actor->getPHID())
      ->setType(ReleephRequestEvent::TYPE_COMMIT)
      ->setDetail('action', $action)
      ->setDetail('newCommitIdentifier', $new_commit_id)
      ->save();

    switch ($action) {
      case 'pick':
        $this->releephRequest
          ->setInBranch(1)
          ->setPickStatus(ReleephRequest::PICK_OK)
          ->setCommitIdentifier($new_commit_id)
          ->setCommitPHID(null)
          ->setCommittedByUserPHID($actor->getPHID())
          ->save();
        break;

      case 'revert':
        $this->releephRequest
          ->setInBranch(0)
          ->setPickStatus(ReleephRequest::REVERT_OK)
          ->setCommitIdentifier(null)
          ->setCommitPHID(null)
          ->setCommittedByUserPHID(null)
          ->save();
        break;

      default:
        $table->killTransaction();
        throw new Exception("Unknown action {$action}!");
        break;
    }

    $table->saveTransaction();

    // Don't spam people about local commits -- we'll do that with
    // discoverCommit() instead!
  }

  /**
   * Mark this request as picked or reverted based on discovering it in the
   * branch.  We have a PhabricatorRepositoryCommit, so we're able to
   * setCommitPHID on the ReleephRequest (unlike recordSuccessfulCommit()).
   */
  public function discoverCommit(
    $action,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $table = $this->releephRequest;
    $table->openTransaction();
    $table->beginWriteLocking();

    $past_events = id(new ReleephRequestEvent())->loadAllWhere(
      'releephRequestID = %d AND type = %s',
      $this->releephRequest->getID(),
      ReleephRequestEvent::TYPE_DISCOVERY);

    foreach ($past_events as $past_event) {
      if ($past_event->getDetail('newCommitIdentifier')
            == $commit->getCommitIdentifier()) {

        // Avoid re-discovery if reparsing!
        $table->endWriteLocking();
        $table->killTransaction();
        return;
      }
    }

    $actor = $this->requireActor();

    $event = id(new ReleephRequestEvent())
      ->setReleephRequestID($this->releephRequest->getID())
      ->setActorPHID($actor->getPHID())
      ->setType(ReleephRequestEvent::TYPE_DISCOVERY)
      ->setDateCreated($commit->getEpoch())
      ->setDetail('action', $action)
      ->setDetail('newCommitIdentifier', $commit->getCommitIdentifier())
      ->setDetail('newCommitPHID', $commit->getPHID())
      ->setDetail('authorPHID', $data->getCommitDetail('authorPHID'))
      ->setDetail('committerPHID', $data->getCommitDetail('committerPHID'))
      ->save();

    switch ($action) {
      case 'pick':
        $this->releephRequest
          ->setInBranch(1)
          ->setPickStatus(ReleephRequest::PICK_OK)
          ->setCommitIdentifier($commit->getCommitIdentifier())
          ->setCommitPHID($commit->getPHID())
          ->setCommittedByUserPHID($actor->getPHID())
          ->save();
        break;

      case 'revert':
        $this->releephRequest
          ->setInBranch(0)
          ->setPickStatus(ReleephRequest::REVERT_OK)
          ->setCommitIdentifier(null)
          ->setCommitPHID(null)
          ->setCommittedByUserPHID(null)
          ->save();
        break;

      default:
        $table->killTransaction();
        throw new Exception("Unknown action {$action}!");
        break;
    }

    $table->endWriteLocking();
    $table->saveTransaction();

    // Mail
    if (!$this->silentUpdate) {
      $project = $this->releephRequest->loadReleephProject();
      $mail = id(new ReleephRequestMail())
        ->setReleephRequest($this->releephRequest)
        ->setReleephProject($project)
        ->setEvents(array($event))
        ->setSenderAndRecipientPHID($this->requireActor()->getPHID())
        ->addTos(ReleephRequestMail::ENT_REQUESTOR)
        ->addCCs(ReleephRequestMail::ENT_ACTORS)
        ->addCCs(ReleephRequestMail::ENT_INTERESTED_PUSHERS)
        ->send();
    }
  }

  public function addComment($comment) {
    $event = $this->newEvent()
      ->setType(ReleephRequestEvent::TYPE_COMMENT)
      ->setDetail('comment', $comment);
    $this->commit();

    // Mail
    if (!$this->silentUpdate) {
      $project = $this->releephRequest->loadReleephProject();
      $mail = id(new ReleephRequestMail())
        ->setReleephRequest($this->releephRequest)
        ->setReleephProject($project)
        ->setEvents(array($event))
        ->setSenderAndRecipientPHID($this->requireActor()->getPHID())
        ->addTos(ReleephRequestMail::ENT_REQUESTOR)
        ->addCCs(ReleephRequestMail::ENT_ACTORS)
        ->addCCs(ReleephRequestMail::ENT_INTERESTED_PUSHERS)
        ->send();
    }
  }

  public function markManuallyActioned($action) {
    $event = $this->newEvent()
      ->setType(ReleephRequestEvent::TYPE_MANUAL_ACTION)
      ->setDetail('action', $action);

    $actor = $this->requireActor();
    $project = $this->releephRequest->loadReleephProject();
    $requestor_phid = $this->releephRequest->getRequestUserPHID();
    if (!$project->isPusher($actor) &&
        $actor->getPHID() !== $requestor_phid) {

      throw new Exception(
        "Only pushers or requestors can mark requests as ".
        "manually picked or reverted!");
    }

    switch ($action) {
      case 'pick':
        $in_branch = true;
        $intent = ReleephRequest::INTENT_WANT;
        break;

      case 'revert':
        $in_branch = false;
        $intent = ReleephRequest::INTENT_PASS;
        break;

      default:
        throw new Exception("Unknown action {$action}!");
        break;
    }

    $this->releephRequest
      ->setInBranch((int)$in_branch)
      ->setUserIntent($this->getActor(), $intent);

    $this->commit();

    // Mail
    if (!$this->silentUpdate) {
      $project = $this->releephRequest->loadReleephProject();
      $mail = id(new ReleephRequestMail())
        ->setReleephRequest($this->releephRequest)
        ->setReleephProject($project)
        ->setEvents(array($event))
        ->setSenderAndRecipientPHID($this->requireActor()->getPHID())
        ->addTos(ReleephRequestMail::ENT_REQUESTOR)
        ->addCCs(ReleephRequestMail::ENT_INTERESTED_PUSHERS)
        ->send();
    }
  }

/* -(  Implementation  )----------------------------------------------------- */

  /**
   * Create and return a new ReleephRequestEvent bound to the editor's
   * ReleephRequest, inside a transaction.
   *
   * When you call commit(), the event and this editor's ReleephRequest (along
   * with any changes you made to the ReleephRequest) are saved and the
   * transaction committed.
   */
  private function newEvent() {
    $actor = $this->requireActor();

    if ($this->event) {
      throw new Exception("You have already called newEvent()!");
    }
    $rq = $this->releephRequest;
    $rq->openTransaction();

    $this->event = id(new ReleephRequestEvent())
      ->setReleephRequestID($rq->getID())
      ->setActorPHID($actor->getPHID())
      ->setStatusBefore($rq->getStatus());

    return $this->event;
  }

  private function commit() {
    if (!$this->event) {
      throw new Exception("You must call newEvent first!");
    }
    $rq = $this->releephRequest;
    $this->event
      ->setStatusAfter($rq->getStatus())
      ->save();
    $rq->save();
    $rq->saveTransaction();
    $this->event = null;
  }

}
