<?php

final class DrydockResourceReclaimLogType extends DrydockLogType {

  const LOGCONST = 'core.resource.reclaim';

  public function getLogTypeName() {
    return pht('Reclaimed');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-refresh red';
  }

  public function renderLog(array $data) {
    $reclaimer_phid = idx($data, 'reclaimerPHID');

    return pht(
      'Resource reclaimed by %s.',
      $this->renderHandle($reclaimer_phid));
  }

}
