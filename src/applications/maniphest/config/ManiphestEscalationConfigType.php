<?php

final class ManiphestEscalationConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'maniphest.escalation';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    ManiphestTaskEscalation::validateConfiguration($value);
  }

}
