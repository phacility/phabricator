<?php

final class DiffusionChangeHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'diffusion.change';

  public function getGroupLabel() {
    return pht('Change Details');
  }

  protected function getGroupOrder() {
    return 1500;
  }

}
