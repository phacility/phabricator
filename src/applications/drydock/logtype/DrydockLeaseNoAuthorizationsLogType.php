<?php

final class DrydockLeaseNoAuthorizationsLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.no-authorizations';

  public function getLogTypeName() {
    return pht('No Authorizations');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-map-o red';
  }

  public function renderLog(array $data) {
    $viewer = $this->getViewer();
    $authorizing_phid = idx($data, 'authorizingPHID');

    return pht(
      'The object which authorized this lease (%s) is not authorized to use '.
      'any of the blueprints the lease lists. Approve the authorizations '.
      'before using the lease.',
      $viewer->renderHandle($authorizing_phid)->render());
  }

}
