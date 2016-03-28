<?php

final class AlmanacServicePropertyEditEngine
  extends AlmanacPropertyEditEngine {

  const ENGINECONST = 'almanac.service.property';

  protected function newObjectQuery() {
    return new AlmanacServiceQuery();
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getObjectName() {
    return pht('Property');
  }

}
