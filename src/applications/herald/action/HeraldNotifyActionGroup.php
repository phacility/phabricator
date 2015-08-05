<?php

final class HeraldNotifyActionGroup extends HeraldActionGroup {

  const ACTIONGROUPKEY = 'notify';

  public function getGroupLabel() {
    return pht('Notify');
  }

  protected function getGroupOrder() {
    return 2000;
  }

}
