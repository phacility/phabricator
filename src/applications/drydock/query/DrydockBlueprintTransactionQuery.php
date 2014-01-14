<?php

final class DrydockBlueprintTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new DrydockBlueprintTransaction();
  }

}
