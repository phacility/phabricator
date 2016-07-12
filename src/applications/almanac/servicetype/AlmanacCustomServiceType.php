<?php

final class AlmanacCustomServiceType extends AlmanacServiceType {

  const SERVICETYPE = 'almanac.custom';

  public function getServiceTypeShortName() {
    return pht('Custom');
  }

  public function getServiceTypeName() {
    return pht('Custom Service');
  }

  public function getServiceTypeDescription() {
    return pht('Defines a unstructured custom service.');
  }

}
