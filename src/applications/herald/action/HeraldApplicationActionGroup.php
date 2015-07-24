<?php

final class HeraldApplicationActionGroup extends HeraldActionGroup {

  const ACTIONGROUPKEY = 'application';

  public function getGroupLabel() {
    return pht('Application');
  }

  protected function getGroupOrder() {
    return 1500;
  }

}
