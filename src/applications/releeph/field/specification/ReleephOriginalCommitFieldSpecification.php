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
    $rr = $this->getReleephRequest();
    $handles = $rr->getHandles();
    return $handles[$rr->getRequestCommitPHID()]->renderLink();
  }

}
