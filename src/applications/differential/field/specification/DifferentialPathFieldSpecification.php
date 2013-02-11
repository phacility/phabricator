<?php

final class DifferentialPathFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return PhabricatorEnv::getEnvConfig('differential.show-host-field');
  }

  public function renderLabelForRevisionView() {
    return 'Path:';
  }

  public function renderValueForRevisionView() {
    $diff = $this->getManualDiff();

    $path = $diff->getSourcePath();
    if (!$path) {
      return null;
    }

    return $path;
  }

}
