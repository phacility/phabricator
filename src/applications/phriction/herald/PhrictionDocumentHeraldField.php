<?php

abstract class PhrictionDocumentHeraldField extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof PhrictionDocument);
  }

  public function getFieldGroupKey() {
    return PhrictionDocumentHeraldFieldGroup::FIELDGROUPKEY;
  }
}
