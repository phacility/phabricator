<?php

abstract class DifferentialRevisionDetailRenderer {
  private $diff;
  private $vsDiff;

  final public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  final protected function getDiff() {
    return $this->diff;
  }

  final public function setVSDiff(DifferentialDiff $diff) {
    $this->vsDiff = $diff;
    return $this;
  }

  final protected function getVSDiff() {
    return $this->vsDiff;
  }

  /**
   * This function must return an array of action links that will be
   * added to the end of action links on the differential revision
   * page. Each element in the array must be an array which must
   * contain 'name' and 'href' fields. 'name' will be the name of the
   * link and 'href' will be the address where the link points
   * to. 'class' is optional and can be used for specifying a CSS
   * class.
   */
  abstract public function generateActionLinks(DifferentialRevision $revision,
                                               DifferentialDiff $diff);
}
