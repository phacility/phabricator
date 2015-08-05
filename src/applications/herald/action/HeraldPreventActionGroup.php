<?php

final class HeraldPreventActionGroup extends HeraldActionGroup {

  const ACTIONGROUPKEY = 'prevent';

  public function getGroupLabel() {
    return pht('Prevent');
  }

  protected function getGroupOrder() {
    return 3000;
  }

}
