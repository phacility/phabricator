<?php

final class HarbormasterBuildStepTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new HarbormasterBuildStepTransaction();
  }

}
