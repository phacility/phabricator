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

}
