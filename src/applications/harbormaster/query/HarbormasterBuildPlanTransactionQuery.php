<?php

final class HarbormasterBuildPlanTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new HarbormasterBuildPlanTransaction();
  }

}
