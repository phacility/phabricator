<?php

abstract class AlmanacManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function loadServices(array $names) {
    if (!$names) {
      return array();
    }

    $services = id(new AlmanacServiceQuery())
      ->setViewer($this->getViewer())
      ->withNames($names)
      ->execute();

    $services = mpull($services, null, 'getName');
    foreach ($names as $name) {
      if (empty($services[$name])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Service "%s" does not exist or could not be loaded!',
            $name));
      }
    }

    return $services;
  }

  protected function updateServiceLock(AlmanacService $service, $lock) {
    $almanac_phid = id(new PhabricatorAlmanacApplication())->getPHID();

    $xaction = id(new AlmanacServiceTransaction())
      ->setTransactionType(AlmanacServiceTransaction::TYPE_LOCK)
      ->setNewValue((int)$lock);

    $editor = id(new AlmanacServiceEditor())
      ->setActor($this->getViewer())
      ->setActingAsPHID($almanac_phid)
      ->setContentSource($this->newContentSource())
      ->setContinueOnMissingFields(true);

    $editor->applyTransactions($service, array($xaction));
  }

}
