<?php

final class DifferentialHostFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return PhabricatorEnv::getEnvConfig('differential.show-host-field');
  }

  public function renderLabelForRevisionView() {
    return 'Host:';
  }

  public function renderValueForRevisionView() {
    $diff = $this->getManualDiff();
    $host = $diff->getSourceMachine();
    if (!$host) {
      return null;
    }
    return phutil_escape_html($host);
  }

}
