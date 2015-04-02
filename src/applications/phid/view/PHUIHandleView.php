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
  private $asTag;

  public function setHandleList(PhabricatorHandleList $list) {
    $this->handleList = $list;
    return $this;
  }

  public function setHandlePHID($phid) {
    $this->handlePHID = $phid;
    return $this;
  }

  public function setAsTag($tag) {
    $this->asTag = $tag;
    return $this;
  }

  public function render() {
    $handle = $this->handleList[$this->handlePHID];
    if ($this->asTag) {
      return $handle->renderTag();
    } else {
      return $handle->renderLink();
    }
  }

}
