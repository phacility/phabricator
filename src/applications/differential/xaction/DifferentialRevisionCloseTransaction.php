<?php

final class DifferentialRevisionCloseTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.close';
  const ACTIONKEY = 'close';

  protected function getRevisionActionLabel() {
    return pht('Close Revision');
  }

  protected function getRevisionActionDescription() {
    return pht('This revision will be closed.');
  }

  public function getIcon() {
    return 'fa-check';
  }

  public function getColor() {
    return 'indigo';
  }

  protected function getRevisionActionOrder() {
    return 300;
  }

  public function getActionName() {
    return pht('Closed');
  }

  public function generateOldValue($object) {
    return $object->isClosed();
  }

  public function applyInternalEffects($object, $value) {
    $was_accepted = $object->isAccepted();

    $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;
    $object->setStatus($status_closed);

    $object->setProperty(
      DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED,
      $was_accepted);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not close this revision because it has already been '.
          'closed. Only open revisions can be closed.'));
    }

    if (!$object->isAccepted()) {
      throw new Exception(
        pht(
          'You can not close this revision because it has not been accepted. '.
          'Revisions must be accepted before they can be closed.'));
    }

    $config_key = 'differential.always-allow-close';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      if (!$this->isViewerRevisionAuthor($object, $viewer)) {
        throw new Exception(
          pht(
            'You can not close this revision because you are not the '.
            'author. You can only close revisions you own. You can change '.
            'this behavior by adjusting the "%s" setting in Config.',
            $config_key));
      }
    }
  }

  public function getTitle() {
    return pht(
      '%s closed this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s closed %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
