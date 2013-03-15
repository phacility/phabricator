<?php

final class ReleephRevisionFieldSpecification
  extends ReleephFieldSpecification {

  public function getName() {
    return 'Revision';
  }

  public function renderValueForHeaderView() {
    $data = $this
      ->getReleephRequest()
      ->loadPhabricatorRepositoryCommitData();
    if (!$data) {
      return null;
    }

    $phid = $data->getCommitDetail('differential.revisionPHID');
    if (!$phid) {
      return null;
    }

    $handles = $this->getReleephRequest()->getHandles();
    $handle = $handles[$phid];
    $link = $handle
      // Hack to remove the strike-through rendering of diff links
      ->setStatus(null)
      ->renderLink();
    return phutil_tag(
      'div',
      array(
        'class' => 'releeph-header-text-truncated',
      ),
      $link);
  }

}
