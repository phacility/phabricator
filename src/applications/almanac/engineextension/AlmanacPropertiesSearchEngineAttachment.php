<?php

final class AlmanacPropertiesSearchEngineAttachment
  extends AlmanacSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Almanac Properties');
  }

  public function getAttachmentDescription() {
    return pht('Get Almanac properties for the object.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needProperties(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $properties = $this->getAlmanacPropertyList($object);

    return array(
      'properties' => $properties,
    );
  }

}
