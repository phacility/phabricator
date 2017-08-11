<?php

final class DifferentialRevisionAcceptTransaction
  extends DifferentialRevisionReviewTransaction {

  const TRANSACTIONTYPE = 'differential.revision.accept';
  const ACTIONKEY = 'accept';

  protected function getRevisionActionLabel() {
    return pht("Accept Revision \xE2\x9C\x94");
  }

  protected function getRevisionActionDescription() {
    return pht('These changes will be approved.');
  }

  public function getIcon() {
    return 'fa-check-circle-o';
  }

  public function getColor() {
    return 'green';
  }

  protected function getRevisionActionOrder() {
    return 500;
  }

  public function getActionName() {
    return pht('Accepted');
  }

  public function getCommandKeyword() {
    $accept_key = 'differential.enable-email-accept';
    $allow_email_accept = PhabricatorEnv::getEnvConfig($accept_key);
    if (!$allow_email_accept) {
      return null;
    }

    return 'accept';
  }

  public function getCommandAliases() {
    return array();
  }

  public function getCommandSummary() {
    return pht('Accept a revision.');
  }

  protected function getActionOptions(
    PhabricatorUser $viewer,
    DifferentialRevision $revision,
    $include_accepted = false) {

    $reviewers = $revision->getReviewers();

    $options = array();
    $value = array();

    // Put the viewer's user reviewer first, if it exists, so that "Accept as
    // yourself" is always at the top.
    $head = array();
    $tail = array();
    foreach ($reviewers as $key => $reviewer) {
      if ($reviewer->isUser()) {
        $head[$key] = $reviewer;
      } else {
        $tail[$key] = $reviewer;
      }
    }
    $reviewers = $head + $tail;

    $diff_phid = $this->getActiveDiffPHID($revision);
    $reviewer_phids = array();

    // If the viewer isn't a reviewer, add them to the list of options first.
    // This happens when you navigate to some revision you aren't involved in:
    // you can accept and become a reviewer.

    $viewer_phid = $viewer->getPHID();
    if ($viewer_phid) {
      if (!isset($reviewers[$viewer_phid])) {
        $reviewer_phids[$viewer_phid] = $viewer_phid;
      }
    }

    $default_unchecked = array();
    foreach ($reviewers as $reviewer) {
      $reviewer_phid = $reviewer->getReviewerPHID();

      if (!$reviewer->hasAuthority($viewer)) {
        // If the viewer doesn't have authority to act on behalf of a reviewer,
        // we check if they can accept by force.
        if ($revision->canReviewerForceAccept($viewer, $reviewer)) {
          $default_unchecked[$reviewer_phid] = true;
        } else {
          continue;
        }
      }

      if (!$include_accepted) {
        if ($reviewer->isAccepted($diff_phid)) {
          // If a reviewer is already in a full "accepted" state, don't
          // include that reviewer as an option unless we're listing all
          // reviewers, including reviewers who have already accepted.
          continue;
        }
      }

      $reviewer_phids[$reviewer_phid] = $reviewer_phid;
    }

    $handles = $viewer->loadHandles($reviewer_phids);

    $head = array();
    $tail = array();
    foreach ($reviewer_phids as $reviewer_phid) {
      $is_force = isset($default_unchecked[$reviewer_phid]);

      if ($is_force) {
        $tail[] = $reviewer_phid;

        $options[$reviewer_phid] = pht(
          'Force accept as %s',
          $viewer->renderHandle($reviewer_phid));
      } else {
        $head[] = $reviewer_phid;
        $value[] = $reviewer_phid;

        $options[$reviewer_phid] = pht(
          'Accept as %s',
          $viewer->renderHandle($reviewer_phid));
      }
    }

    // Reorder reviewers so "force accept" reviewers come at the end.
    $options =
      array_select_keys($options, $head) +
      array_select_keys($options, $tail);

    return array($options, $value);
  }

  public function generateOldValue($object) {
    $actor = $this->getActor();
    return $this->isViewerFullyAccepted($object, $actor);
  }

  public function applyExternalEffects($object, $value) {
    $status = DifferentialReviewerStatus::STATUS_ACCEPTED;
    $actor = $this->getActor();
    $this->applyReviewerEffect($object, $actor, $value, $status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not accept this revision because it has already been '.
          'closed. Only open revisions can be accepted.'));
    }

    $config_key = 'differential.allow-self-accept';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      if ($this->isViewerRevisionAuthor($object, $viewer)) {
        throw new Exception(
          pht(
            'You can not accept this revision because you are the revision '.
            'author. You can only accept revisions you do not own. You can '.
            'change this behavior by adjusting the "%s" setting in Config.',
            $config_key));
      }
    }

    if ($this->isViewerFullyAccepted($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not accept this revision because you have already '.
          'accepted it.'));
    }
  }

  protected function validateOptionValue($object, $actor, array $value) {
    if (!$value) {
      throw new Exception(
        pht(
          'When accepting a revision, you must accept on behalf of at '.
          'least one reviewer.'));
    }

    // NOTE: We're including reviewers who have already been accepted in this
    // check. Legitimate users may race one another to accept on behalf of
    // packages. If we get a form submission which includes a reviewer which
    // someone has already accepted, that's fine. See T12757.

    list($options) = $this->getActionOptions($actor, $object, true);
    foreach ($value as $phid) {
      if (!isset($options[$phid])) {
        throw new Exception(
          pht(
            'Reviewer "%s" is not a valid reviewer which you have authority '.
            'to accept on behalf of.',
            $phid));
      }
    }
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if (is_array($new) && $new) {
      return pht(
        '%s accepted this revision as %s reviewer(s): %s.',
        $this->renderAuthor(),
        phutil_count($new),
        $this->renderHandleList($new));
    } else {
      return pht(
        '%s accepted this revision.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    return pht(
      '%s accepted %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
