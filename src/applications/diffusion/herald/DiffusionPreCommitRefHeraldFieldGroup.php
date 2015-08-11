<?php

final class DiffusionPreCommitRefHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'diffusion.ref';

  public function getGroupLabel() {
    return pht('Ref Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}
