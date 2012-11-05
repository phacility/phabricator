<?php

final class DifferentialLinesFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Lines:';
  }

  public function renderValueForRevisionView() {
    $diff = $this->getDiff();
    return phutil_escape_html(number_format($diff->getLineCount()));
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Lines';
  }

  public function getColumnClassForRevisionList() {
    return 'n';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return number_format($revision->getLineCount());
  }

}
