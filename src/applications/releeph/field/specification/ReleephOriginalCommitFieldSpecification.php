<?php

final class ReleephOriginalCommitFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'commit:name';
  }

  public function getName() {
    return 'Commit';
  }

  public function renderValueForHeaderView() {
    $pull = $this->getReleephRequest();
    return $this->slowlyLoadHandle($pull->getRequestCommitPHID())->renderLink();
  }

}
