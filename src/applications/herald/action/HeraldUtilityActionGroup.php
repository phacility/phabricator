<?php

final class HeraldUtilityActionGroup extends HeraldActionGroup {

  const ACTIONGROUPKEY = 'utility';

  public function getGroupLabel() {
    return pht('Utility');
  }

  protected function getGroupOrder() {
    return 10000;
  }

}
