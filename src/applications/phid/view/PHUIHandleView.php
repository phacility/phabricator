<?php

/**
 * Convenience class for rendering a single handle.
 *
 * This class simplifies rendering a single handle, and improves loading and
 * caching semantics in the rendering pipeline by loading data at the last
 * moment.
 */

final class PHUIHandleView
  extends AphrontView {

  private $handleList;
  private $handlePHID;

  public function setHandleList(PhabricatorHandleList $list) {
    $this->handleList = $list;
    return $this;
  }

  public function setHandlePHID($phid) {
    $this->handlePHID = $phid;
    return $this;
  }

  public function render() {
    return $this->handleList[$this->handlePHID]->renderLink();
  }

}
