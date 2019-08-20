<?php

final class PhabricatorPeopleManagementEnableWorkflow
  extends PhabricatorPeopleManagementWorkflow {

  protected function didConstruct() {
    $arguments = array_merge(
      $this->getUserSelectionArguments(),
      array());

    $this
      ->setName('enable')
      ->setExamples('**enable** --user __username__')
      ->setSynopsis(pht('Enable a disabled user account.'))
      ->setArguments($arguments);
  }

  public function execute(PhutilArgumentParser $args) {
    $user = $this->selectUser($args);
    $display_name = $user->getUsername();

    if (!$user->getIsDisabled()) {
      throw new PhutilArgumentUsageException(
        pht(
          'User account "%s" is not disabled. You can only enable accounts '.
          'that are disabled.',
          $display_name));
    }

    $xactions = array();
    $xactions[] = $user->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorUserDisableTransaction::TRANSACTIONTYPE)
      ->setNewValue(false);

    $this->applyTransactions($user, $xactions);

    $this->logOkay(
      pht('DONE'),
      pht('Enabled user account "%s".', $display_name));

    return 0;
  }

}
