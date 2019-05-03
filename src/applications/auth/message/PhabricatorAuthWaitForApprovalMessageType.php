<?php

final class PhabricatorAuthWaitForApprovalMessageType
  extends PhabricatorAuthMessageType {

  const MESSAGEKEY = 'auth.wait-for-approval';

  public function getDisplayName() {
    return pht('Wait For Approval Instructions');
  }

  public function getShortDescription() {
    return pht(
      'Instructions on the "Wait For Approval" screen, shown to users who '.
      'have registered an account that has not yet been approved by an '.
      'administrator.');
  }

}
