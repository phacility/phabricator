<?php

final class PhabricatorCalendarSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorCalendarEvent());
  }

}
