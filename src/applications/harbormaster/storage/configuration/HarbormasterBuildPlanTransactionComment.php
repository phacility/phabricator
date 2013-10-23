<?php

final class HarbormasterBuildPlanTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new HarbormasterBuildPlan();
  }

}
