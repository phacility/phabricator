<?php

final class ReleephRequestTransactionalEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorReleephApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Releeph Requests');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = ReleephRequestTransaction::TYPE_COMMIT;
    $types[] = ReleephRequestTransaction::TYPE_DISCOVERY;
    $types[] = ReleephRequestTransaction::TYPE_EDIT_FIELD;
    $types[] = ReleephRequestTransaction::TYPE_MANUAL_IN_BRANCH;
    $types[] = ReleephRequestTransaction::TYPE_PICK_STATUS;
    $types[] = ReleephRequestTransaction::TYPE_REQUEST;
    $types[] = ReleephRequestTransaction::TYPE_USER_INTENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ReleephRequestTransaction::TYPE_REQUEST:
        return $object->getRequestCommitPHID();

      case ReleephRequestTransaction::TYPE_EDIT_FIELD:
        $field = newv($xaction->getMetadataValue('fieldClass'), array());
        $value = $field->setReleephRequest($object)->getValue();
        return $value;

      case ReleephRequestTransaction::TYPE_USER_INTENT:
        $user_phid = $xaction->getAuthorPHID();
        return idx($object->getUserIntents(), $user_phid);

      case ReleephRequestTransaction::TYPE_PICK_STATUS:
        return (int)$object->getPickStatus();
        break;

      case ReleephRequestTransaction::TYPE_COMMIT:
        return $object->getCommitIdentifier();

      case ReleephRequestTransaction::TYPE_DISCOVERY:
        return $object->getCommitPHID();

      case ReleephRequestTransaction::TYPE_MANUAL_IN_BRANCH:
        return $object->getInBranch();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ReleephRequestTransaction::TYPE_REQUEST:
      case ReleephRequestTransaction::TYPE_USER_INTENT:
      case ReleephRequestTransaction::TYPE_EDIT_FIELD:
      case ReleephRequestTransaction::TYPE_PICK_STATUS:
      case ReleephRequestTransaction::TYPE_COMMIT:
      case ReleephRequestTransaction::TYPE_DISCOVERY:
      case ReleephRequestTransaction::TYPE_MANUAL_IN_BRANCH:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case ReleephRequestTransaction::TYPE_REQUEST:
        $object->setRequestCommitPHID($new);
        break;

      case ReleephRequestTransaction::TYPE_USER_INTENT:
        $user_phid = $xaction->getAuthorPHID();
        $intents = $object->getUserIntents();
        $intents[$user_phid] = $new;
        $object->setUserIntents($intents);
        break;

      case ReleephRequestTransaction::TYPE_EDIT_FIELD:
        $field = newv($xaction->getMetadataValue('fieldClass'), array());
        $field
          ->setReleephRequest($object)
          ->setValue($new);
        break;

      case ReleephRequestTransaction::TYPE_PICK_STATUS:
        $object->setPickStatus($new);
        break;

      case ReleephRequestTransaction::TYPE_COMMIT:
        $this->setInBranchFromAction($object, $xaction);
        $object->setCommitIdentifier($new);
        break;

      case ReleephRequestTransaction::TYPE_DISCOVERY:
        $this->setInBranchFromAction($object, $xaction);
        $object->setCommitPHID($new);
        break;

      case ReleephRequestTransaction::TYPE_MANUAL_IN_BRANCH:
        $object->setInBranch((int)$new);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    return;
  }

  protected function filterTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Remove TYPE_DISCOVERY xactions that are the result of a reparse.
    $previously_discovered_commits = array();
    $discovery_xactions = idx(
      mgroup($xactions, 'getTransactionType'),
      ReleephRequestTransaction::TYPE_DISCOVERY);
    if ($discovery_xactions) {
      $previous_xactions = id(new ReleephRequestTransactionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withObjectPHIDs(array($object->getPHID()))
        ->execute();

      foreach ($previous_xactions as $xaction) {
        if ($xaction->getTransactionType() ===
          ReleephRequestTransaction::TYPE_DISCOVERY) {

          $commit_phid = $xaction->getNewValue();
          $previously_discovered_commits[$commit_phid] = true;
        }
      }
    }

    foreach ($xactions as $key => $xaction) {
      if ($xaction->getTransactionType() ===
        ReleephRequestTransaction::TYPE_DISCOVERY &&
        idx($previously_discovered_commits, $xaction->getNewValue())) {

        unset($xactions[$key]);
      }
    }

    return parent::filterTransactions($object, $xactions);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Avoid sending emails that only talk about commit discovery.
    $types = array_unique(mpull($xactions, 'getTransactionType'));
    if ($types === array(ReleephRequestTransaction::TYPE_DISCOVERY)) {
      return false;
    }

    // Don't email people when we discover that something picks or reverts OK.
    if ($types === array(ReleephRequestTransaction::TYPE_PICK_STATUS)) {
      if (!mfilter($xactions, 'isBoringPickStatus', true /* negate */)) {
        // If we effectively call "isInterestingPickStatus" and get nothing...
        return false;
      }
    }

    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new ReleephRequestReplyHandler())
      ->setActor($this->getActor())
      ->setMailReceiver($object);
  }

  protected function getMailSubjectPrefix() {
    return '[Releeph]';
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $phid = $object->getPHID();
    $title = $object->getSummaryForDisplay();
    return id(new PhabricatorMetaMTAMail())
      ->setSubject("RQ{$id}: {$title}")
      ->addHeader('Thread-Topic', "RQ{$id}: {$phid}");
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $to_phids = array();

    $product = $object->getBranch()->getProduct();
    foreach ($product->getPushers() as $phid) {
      $to_phids[] = $phid;
    }

    foreach ($object->getUserIntents() as $phid => $intent) {
      $to_phids[] = $phid;
    }

    return $to_phids;
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return array();
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $rq = $object;
    $releeph_branch = $rq->getBranch();
    $releeph_project = $releeph_branch->getProduct();

    /**
     * If any of the events we are emailing about were about a pick failure
     * (and/or a revert failure?), include pick failure instructions.
     */
    $has_pick_failure = false;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() ===
        ReleephRequestTransaction::TYPE_PICK_STATUS &&
        $xaction->getNewValue() === ReleephRequest::PICK_FAILED) {

        $has_pick_failure = true;
        break;
      }
    }
    if ($has_pick_failure) {
      $instructions = $releeph_project->getDetail('pick_failure_instructions');
      if ($instructions) {
        $body->addTextSection(
          pht('PICK FAILURE INSTRUCTIONS'),
          $instructions);
      }
    }

    $name = sprintf('RQ%s: %s', $rq->getID(), $rq->getSummaryForDisplay());
    $body->addTextSection(
      pht('RELEEPH REQUEST'),
      $name."\n".
      PhabricatorEnv::getProductionURI('/RQ'.$rq->getID()));

    $project_and_branch = sprintf(
      '%s - %s',
      $releeph_project->getName(),
      $releeph_branch->getDisplayNameWithDetail());

    $body->addTextSection(
      pht('RELEEPH BRANCH'),
      $project_and_branch."\n".
      PhabricatorEnv::getProductionURI($releeph_branch->getURI()));

    return $body;
  }

  private function setInBranchFromAction(
    ReleephRequest $rq,
    ReleephRequestTransaction $xaction) {

    $action = $xaction->getMetadataValue('action');
    switch ($action) {
      case 'pick':
        $rq->setInBranch(1);
        break;

      case 'revert':
        $rq->setInBranch(0);
        break;

      default:
        $id = $rq->getID();
        $type = $xaction->getTransactionType();
        $new = $xaction->getNewValue();
        phlog(
          pht(
            "Unknown discovery action '%s' for xaction of type %s ".
            "with new value %s mentioning %s!",
            $action,
            $type,
            $new,
            'RQ'.$id));
        break;
    }
  }

}
