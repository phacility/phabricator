<?php

final class DiffusionCommitAcceptTransaction
  extends DiffusionCommitAuditTransaction {

  const TRANSACTIONTYPE = 'diffusion.commit.accept';
  const ACTIONKEY = 'accept';

  protected function getCommitActionLabel() {
    return pht("Accept Commit \xE2\x9C\x94");
  }

  protected function getCommitActionDescription() {
    return pht('This commit will be approved.');
  }

  public function getIcon() {
    return 'fa-check-circle-o';
  }

  public function getColor() {
    return 'green';
  }

  protected function getCommitActionOrder() {
    return 500;
  }

  public function getActionName() {
    return pht('Accepted');
  }

  public function applyExternalEffects($object, $value) {
    $status = PhabricatorAuditStatusConstants::ACCEPTED;
    $actor = $this->getActor();
    $this->applyAuditorEffect($object, $actor, $value, $status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    $config_key = 'audit.can-author-close-audit';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      if ($this->isViewerCommitAuthor($object, $viewer)) {
        throw new Exception(
          pht(
            'You can not accept this commit because you are the commit '.
            'author. You can only accept commits you did not author. You can '.
            'change this behavior by adjusting the "%s" setting in Config.',
            $config_key));
      }
    }

    if ($this->isViewerFullyAccepted($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not accept this commit because you have already '.
          'accepted it.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s accepted this commit.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s accepted %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
