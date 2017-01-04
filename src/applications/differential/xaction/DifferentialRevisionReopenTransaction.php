<?php

final class DifferentialRevisionReopenTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.reopen';
  const ACTIONKEY = 'reopen';

  protected function getRevisionActionLabel() {
    return pht('Reopen Revision');
  }

  protected function getRevisionActionDescription() {
    return pht('This revision will be reopened for review.');
  }

  public function getIcon() {
    return 'fa-bullhorn';
  }

  public function getColor() {
    return 'sky';
  }

  protected function getRevisionActionOrder() {
    return 400;
  }

  public function generateOldValue($object) {
    return !$object->isClosed();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    // Note that we're testing for "Closed", exactly, not just any closed
    // status.
    $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;
    if ($object->getStatus() != $status_closed) {
      throw new Exception(
        pht(
          'You can not reopen this revision because it is not closed. '.
          'Only closed revisions can be reopened.'));
    }

    $config_key = 'differential.allow-reopen';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      throw new Exception(
        pht(
          'You can not reopen this revision because configuration prevents '.
          'any revision from being reopened. You can change this behavior '.
          'by adjusting the "%s" setting in Config.',
          $config_key));
    }
  }

  public function getTitle() {
    return pht(
      '%s reopened this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s reopened %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
