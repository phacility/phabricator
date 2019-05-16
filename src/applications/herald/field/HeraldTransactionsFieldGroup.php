<?php

final class HeraldTransactionsFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'transactions';

  public function getGroupLabel() {
    return pht('Transactions');
  }

  protected function getGroupOrder() {
    return 2000;
  }

}
