<?php

/**
 * @group harbormaster
 */
final class HarbormasterBuildableTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new HarbormasterBuildableTransaction();
  }

}
