<?php

final class DifferentialExportPatchFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Export Patch:';
  }

  public function renderValueForRevisionView() {
    $revision = $this->getRevision();
    return '<tt>arc export --revision '.$revision->getID().'</tt>';
  }

}
