<?php

final class AlmanacBindingPropertyEditEngine
  extends AlmanacPropertyEditEngine {

  const ENGINECONST = 'almanac.binding.property';

  protected function newObjectQuery() {
    return new AlmanacBindingQuery();
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getObjectName() {
    return pht('Property');
  }

}
