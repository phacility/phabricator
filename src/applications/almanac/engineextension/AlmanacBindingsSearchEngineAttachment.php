<?php

final class AlmanacBindingsSearchEngineAttachment
  extends AlmanacSearchEngineAttachment {

  private $isActive;

  public function setIsActive($is_active) {
    $this->isActive = $is_active;
    return $this;
  }

  public function getIsActive() {
    return $this->isActive;
  }

  public function getAttachmentName() {
    return pht('Almanac Bindings');
  }

  public function getAttachmentDescription() {
    return pht('Get Almanac bindings for the service.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needProperties(true);

    if ($this->getIsActive()) {
      $query->needActiveBindings(true);
    } else {
      $query->needBindings(true);
    }
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $bindings = array();

    if ($this->getIsActive()) {
      $service_bindings = $object->getActiveBindings();
    } else {
      $service_bindings = $object->getBindings();
    }

    foreach ($service_bindings as $binding) {
      $bindings[] = $this->getAlmanacBindingDictionary($binding);
    }

    return array(
      'bindings' => $bindings,
    );
  }

}
