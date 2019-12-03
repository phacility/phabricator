<?php

final class PhabricatorPeopleManagementEmpowerWorkflow
  extends PhabricatorPeopleManagementWorkflow {

  protected function didConstruct() {
    $arguments = array_merge(
      $this->getUserSelectionArguments(),
      array());

    $this
      ->setName('empower')
      ->setExamples('**empower** --user __username__')
      ->setSynopsis(pht('Turn a user account into an administrator account.'))
      ->setArguments($arguments);
  }

  public function execute(PhutilArgumentParser $args) {
    $user = $this->selectUser($args);
    $display_name = $user->getUsername();

    if ($user->getIsAdmin()) {
      throw new PhutilArgumentUsageException(
        pht(
          'User account "%s" is already an administrator. You can only '.
          'empower accounts that are not yet administrators.',
          $display_name));
    }

    $xactions = array();
    $xactions[] = $user->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorUserEmpowerTransaction::TRANSACTIONTYPE)
      ->setNewValue(true);

    $this->applyTransactions($user, $xactions);

    $this->logOkay(
      pht('DONE'),
      pht('Empowered user account "%s".', $display_name));

    return 0;
  }

}
