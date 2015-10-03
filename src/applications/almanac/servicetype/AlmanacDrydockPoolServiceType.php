<?php

final class AlmanacDrydockPoolServiceType extends AlmanacServiceType {

  public function getServiceTypeShortName() {
    return pht('Drydock Pool');
  }

  public function getServiceTypeName() {
    return pht('Drydock: Resource Pool');
  }

  public function getServiceTypeDescription() {
    return pht(
      'Defines a pool of hosts which Drydock can allocate.');
  }

}
