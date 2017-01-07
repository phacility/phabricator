<?php

final class DifferentialRevisionAbandonTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.abandon';
  const ACTIONKEY = 'abandon';

  protected function getRevisionActionLabel() {
    return pht('Abandon Revision');
  }

  protected function getRevisionActionDescription() {
    return pht('This revision will be abandoned and closed.');
  }

  public function getIcon() {
    return 'fa-plane';
  }

  public function getColor() {
    return 'indigo';
  }

  protected function getRevisionActionOrder() {
    return 500;
  }

  public function getCommandKeyword() {
    return 'abandon';
  }

  public function getCommandAliases() {
    return array();
  }

  public function getCommandSummary() {
    return pht('Abandon a revision.');
  }

  public function generateOldValue($object) {
    return $object->isAbandoned();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(ArcanistDifferentialRevisionStatus::ABANDONED);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not abandon this revision because it has already been '.
          'closed. Only open revisions can be abandoned.'));
    }

    $config_key = 'differential.always-allow-abandon';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      if (!$this->isViewerRevisionAuthor($object, $viewer)) {
        throw new Exception(
          pht(
            'You can not abandon this revision because you are not the '.
            'author. You can only abandon revisions you own. You can change '.
            'this behavior by adjusting the "%s" setting in Config.',
            $config_key));
      }
    }
  }

  public function getTitle() {
    return pht(
      '%s abandoned this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s abandoned %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
