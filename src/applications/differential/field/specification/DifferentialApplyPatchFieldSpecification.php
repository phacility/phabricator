<?php

final class DifferentialApplyPatchFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Apply Patch:';
  }

  public function renderValueForRevisionView() {
    $revision = $this->getRevision();
    return phutil_tag('tt', array(), 'arc patch D'.$revision->getID());
  }

}
