<?php

final class NuanceQueueTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new NuanceQueueTransaction();
  }

}
