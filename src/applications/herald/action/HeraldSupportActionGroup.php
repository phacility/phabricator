<?php

final class HeraldSupportActionGroup extends HeraldActionGroup {

  const ACTIONGROUPKEY = 'support';

  public function getGroupLabel() {
    return pht('Supporting Applications');
  }

  protected function getGroupOrder() {
    return 4000;
  }

}
