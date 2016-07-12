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
  private $asText;
  private $useShortName;
  private $showHovercard;

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

  public function setAsText($as_text) {
    $this->asText = $as_text;
    return $this;
  }

  public function setUseShortName($short) {
    $this->useShortName = $short;
    return $this;
  }

  public function setShowHovercard($hovercard) {
    $this->showHovercard = $hovercard;
    return $this;
  }

  public function render() {
    $handle = $this->handleList[$this->handlePHID];

    if ($this->asTag) {
      $tag = $handle->renderTag();

      if ($this->showHovercard) {
        $tag->setPHID($handle->getPHID());
      }

      return $tag;
    }

    if ($this->asText) {
      return $handle->getLinkName();
    }

    if ($this->useShortName) {
      $name = $handle->getName();
    } else {
      $name = null;
    }

    if ($this->showHovercard) {
      $link = $handle->renderHovercardLink($name);
    } else {
      $link = $handle->renderLink($name);
    }

    return $link;
  }

}
