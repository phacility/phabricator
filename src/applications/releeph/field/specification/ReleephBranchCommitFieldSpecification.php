<?php

final class ReleephBranchCommitFieldSpecification
  extends ReleephFieldSpecification {

  public function getName() {
    return 'Commit';
  }

  public function renderValueForHeaderView() {
    $rr = $this->getReleephRequest();
    if (!$rr->getInBranch()) {
      return null;
    }

    $c_phid = $rr->getCommitPHID();
    $c_id = $rr->getCommitIdentifier();

    if ($c_phid) {
      $handles = $rr->getHandles();
      $val = $handles[$c_phid]->renderLink();
    } else if ($c_id) {
      $val = $c_id;
    } else {
      $val = '???';
    }
    return $val;
  }

}
