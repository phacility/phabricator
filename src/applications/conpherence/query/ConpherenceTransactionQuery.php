<?php

final class ConpherenceTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ConpherenceTransaction();
  }

  protected function getDefaultOrderVector() {
    // TODO: Can we get rid of this?
    return array('-id');
  }

}
