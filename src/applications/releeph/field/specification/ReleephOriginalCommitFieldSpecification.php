<?php

final class ReleephOriginalCommitFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'commit:name';
  }

  public function getName() {
    return 'Commit';
  }

  public function renderPropertyViewValue(array $handles) {
    $pull = $this->getReleephRequest();
    return $this->slowlyLoadHandle($pull->getRequestCommitPHID())->renderLink();
  }

}
