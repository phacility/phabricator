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
    $viewer = $this->getViewer();
    $reclaimer_phid = idx($data, 'reclaimerPHID');

    return pht(
      'Resource reclaimed by %s.',
      $viewer->renderHandle($reclaimer_phid)->render());
  }

}
