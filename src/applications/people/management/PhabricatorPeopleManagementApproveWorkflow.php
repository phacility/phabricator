<?php


final class PhabricatorPeopleManagementApproveWorkflow
  extends PhabricatorPeopleManagementWorkflow {

  protected function didConstruct() {
    $arguments = array_merge(
      $this->getUserSelectionArguments(),
      array());

    $this
      ->setName('approve')
      ->setExamples('**approve** --user __username__')
      ->setSynopsis(pht('Approves a user.'))
      ->setArguments($arguments);
  }

  public function execute(PhutilArgumentParser $args) {
    $user = $this->selectUser($args);
    $display_name = $user->getUsername();

    if ($user->getIsApproved()) {
      throw new PhutilArgumentUsageException(
        pht(
          'User account "%s" is already approved. You can only '.
          'approve accounts that are not yet approved.',
          $display_name));
    }

    $xactions = array();
    $xactions[] = $user->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorUserApproveTransaction::TRANSACTIONTYPE)
      ->setNewValue(true);

    $this->applyTransactions($user, $xactions);

    $this->logOkay(
      pht('DONE'),
      pht('Approved user account "%s".', $display_name));

    return 0;
  }
}
