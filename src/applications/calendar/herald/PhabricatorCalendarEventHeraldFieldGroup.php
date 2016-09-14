<?php

final class PhabricatorCalendarEventHeraldFieldGroup
  extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'calendar.event';

  public function getGroupLabel() {
    return pht('Event Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}
