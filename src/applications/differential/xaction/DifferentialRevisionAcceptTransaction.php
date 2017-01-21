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

  public function getTitle() {
    return pht(
      '%s accepted this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s accepted %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
