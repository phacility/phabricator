<?php

abstract class PhabricatorCalendarEventHeraldField extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof PhabricatorCalendarEvent);
  }

  public function getFieldGroupKey() {
    return PhabricatorCalendarEventHeraldFieldGroup::FIELDGROUPKEY;
  }

}
