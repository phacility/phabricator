<?php

final class PhabricatorTypeaheadResult extends Phobject {

  private $name;
  private $uri;
  private $phid;
  private $priorityString;
  private $displayName;
  private $displayType;
  private $imageURI;
  private $priorityType;
  private $imageSprite;
  private $icon;
  private $color;
  private $closed;
  private $tokenType;
  private $unique;
  private $autocomplete;

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function setPriorityString($priority_string) {
    $this->priorityString = $priority_string;
    return $this;
  }

  public function setDisplayName($display_name) {
    $this->displayName = $display_name;
    return $this;
  }

  public function setDisplayType($display_type) {
    $this->displayType = $display_type;
    return $this;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }

  public function setPriorityType($priority_type) {
    $this->priorityType = $priority_type;
    return $this;
  }

  public function setImageSprite($image_sprite) {
    $this->imageSprite = $image_sprite;
    return $this;
  }

  public function setClosed($closed) {
    $this->closed = $closed;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function getDisplayName() {
    return coalesce($this->displayName, $this->getName());
  }

  public function getIcon() {
    return nonempty($this->icon, $this->getDefaultIcon());
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setUnique($unique) {
    $this->unique = $unique;
    return $this;
  }

  public function setTokenType($type) {
    $this->tokenType = $type;
    return $this;
  }

  public function getTokenType() {
    if ($this->closed && !$this->tokenType) {
      return PhabricatorTypeaheadTokenView::TYPE_DISABLED;
    }
    return $this->tokenType;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setAutocomplete($autocomplete) {
    $this->autocomplete = $autocomplete;
    return $this;
  }

  public function getAutocomplete() {
    return $this->autocomplete;
  }

  public function getSortKey() {
    // Put unique results (special parameter functions) ahead of other
    // results.
    if ($this->unique) {
      $prefix = 'A';
    } else {
      $prefix = 'B';
    }

    return $prefix.phutil_utf8_strtolower($this->getName());
  }

  public function getWireFormat() {
    $data = array(
      $this->name,
      $this->uri ? (string)$this->uri : null,
      $this->phid,
      $this->priorityString,
      $this->displayName,
      $this->displayType,
      $this->imageURI ? (string)$this->imageURI : null,
      $this->priorityType,
      $this->getIcon(),
      $this->closed,
      $this->imageSprite ? (string)$this->imageSprite : null,
      $this->color,
      $this->tokenType,
      $this->unique ? 1 : null,
      $this->autocomplete,
    );
    while (end($data) === null) {
      array_pop($data);
    }
    return $data;
  }

  /**
   * If the datasource did not specify an icon explicitly, try to select a
   * default based on PHID type.
   */
  private function getDefaultIcon() {
    static $icon_map;
    if ($icon_map === null) {
      $types = PhabricatorPHIDType::getAllTypes();

      $map = array();
      foreach ($types as $type) {
        $icon = $type->getTypeIcon();
        if ($icon !== null) {
          $map[$type->getTypeConstant()] = $icon;
        }
      }

      $icon_map = $map;
    }

    $phid_type = phid_get_type($this->phid);
    if (isset($icon_map[$phid_type])) {
      return $icon_map[$phid_type];
    }

    return null;
  }

}
