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
    return '<tt>arc patch D'.$revision->getID().'</tt>';
  }

}
