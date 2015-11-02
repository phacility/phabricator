<?php

final class DrydockLeaseNoBlueprintsLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.no-blueprints';

  public function getLogTypeName() {
    return pht('No Blueprints');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-map-o red';
  }

  public function renderLog(array $data) {
    return pht('This lease does not list any usable blueprints.');
  }

}
