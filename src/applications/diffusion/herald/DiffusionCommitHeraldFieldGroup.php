<?php

final class DiffusionCommitHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'diffusion.commit';

  public function getGroupLabel() {
    return pht('Commit Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}
