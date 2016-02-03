<?php

final class PhabricatorObjectHandle
  extends Phobject
  implements PhabricatorPolicyInterface {

  const AVAILABILITY_FULL = 'full';
  const AVAILABILITY_NONE = 'none';
  const AVAILABILITY_PARTIAL = 'partial';
  const AVAILABILITY_DISABLED = 'disabled';

  const STATUS_OPEN = 'open';
  const STATUS_CLOSED = 'closed';

  private $uri;
  private $phid;
  private $type;
  private $name;
  private $fullName;
  private $title;
  private $imageURI;
  private $icon;
  private $tagColor;
  private $timestamp;
  private $status = self::STATUS_OPEN;
  private $availability = self::AVAILABILITY_FULL;
  private $complete;
  private $objectName;
  private $policyFiltered;
  private $subtitle;
  private $tokenIcon;

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    if ($this->getPolicyFiltered()) {
      return 'fa-lock';
    }

    if ($this->icon) {
      return $this->icon;
    }
    return $this->getTypeIcon();
  }

  public function setSubtitle($subtitle) {
    $this->subtitle = $subtitle;
    return $this;
  }

  public function getSubtitle() {
    return $this->subtitle;
  }

  public function setTagColor($color) {
    static $colors;
    if (!$colors) {
      $colors = array_fuse(array_keys(PHUITagView::getShadeMap()));
    }

    if (isset($colors[$color])) {
      $this->tagColor = $color;
    }

    return $this;
  }

  public function getTagColor() {
    if ($this->getPolicyFiltered()) {
      return 'disabled';
    }

    if ($this->tagColor) {
      return $this->tagColor;
    }

    return 'blue';
  }

  public function getIconColor() {
    if ($this->tagColor) {
      return $this->tagColor;
    }
    return null;
  }

  public function setTokenIcon($icon) {
    $this->tokenIcon = $icon;
    return $this;
  }

  public function getTokenIcon() {
    if ($this->tokenIcon !== null) {
      return $this->tokenIcon;
    }

    return $this->getIcon();
  }

  public function getTypeIcon() {
    if ($this->getPHIDType()) {
      return $this->getPHIDType()->getTypeIcon();
    }
    return null;
  }

  public function setPolicyFiltered($policy_filered) {
    $this->policyFiltered = $policy_filered;
    return $this;
  }

  public function getPolicyFiltered() {
    return $this->policyFiltered;
  }

  public function setObjectName($object_name) {
    $this->objectName = $object_name;
    return $this;
  }

  public function getObjectName() {
    if (!$this->objectName) {
      return $this->getName();
    }
    return $this->objectName;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    if ($this->name === null) {
      if ($this->getPolicyFiltered()) {
        return pht('Restricted %s', $this->getTypeName());
      } else {
        return pht('Unknown Object (%s)', $this->getTypeName());
      }
    }
    return $this->name;
  }

  public function setAvailability($availability) {
    $this->availability = $availability;
    return $this;
  }

  public function getAvailability() {
    return $this->availability;
  }

  public function isDisabled() {
    return ($this->getAvailability() == self::AVAILABILITY_DISABLED);
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

  public function setFullName($full_name) {
    $this->fullName = $full_name;
    return $this;
  }

  public function getFullName() {
    if ($this->fullName !== null) {
      return $this->fullName;
    }
    return $this->getName();
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setImageURI($uri) {
    $this->imageURI = $uri;
    return $this;
  }

  public function getImageURI() {
    return $this->imageURI;
  }

  public function setTimestamp($timestamp) {
    $this->timestamp = $timestamp;
    return $this;
  }

  public function getTimestamp() {
    return $this->timestamp;
  }

  public function getTypeName() {
    if ($this->getPHIDType()) {
      return $this->getPHIDType()->getTypeName();
    }

    return $this->getType();
  }


  /**
   * Set whether or not the underlying object is complete. See
   * @{method:isComplete} for an explanation of what it means to be complete.
   *
   * @param bool True if the handle represents a complete object.
   * @return this
   */
  public function setComplete($complete) {
    $this->complete = $complete;
    return $this;
  }


  /**
   * Determine if the handle represents an object which was completely loaded
   * (i.e., the underlying object exists) vs an object which could not be
   * completely loaded (e.g., the type or data for the PHID could not be
   * identified or located).
   *
   * Basically, @{class:PhabricatorHandleQuery} gives you back a handle for
   * any PHID you give it, but it gives you a complete handle only for valid
   * PHIDs.
   *
   * @return bool True if the handle represents a complete object.
   */
  public function isComplete() {
    return $this->complete;
  }


  public function renderLink($name = null) {
    return $this->renderLinkWithAttributes($name, array());
  }

  public function renderHovercardLink($name = null) {
    Javelin::initBehavior('phui-hovercards');

    $attributes = array(
      'sigil' => 'hovercard',
      'meta' => array(
        'hoverPHID' => $this->getPHID(),
      ),
    );

    return $this->renderLinkWithAttributes($name, $attributes);
  }

  private function renderLinkWithAttributes($name, array $attributes) {
    if ($name === null) {
      $name = $this->getLinkName();
    }
    $classes = array();
    $classes[] = 'phui-handle';
    $title = $this->title;

    if ($this->status != self::STATUS_OPEN) {
      $classes[] = 'handle-status-'.$this->status;
    }

    if ($this->availability != self::AVAILABILITY_FULL) {
      $classes[] = 'handle-availability-'.$this->availability;
    }

    if ($this->getType() == PhabricatorPeopleUserPHIDType::TYPECONST) {
      $classes[] = 'phui-link-person';
    }

    $uri = $this->getURI();

    $icon = null;
    if ($this->getPolicyFiltered()) {
      $icon = id(new PHUIIconView())
        ->setIcon('fa-lock lightgreytext');
    }

    $attributes = $attributes + array(
      'href'  => $uri,
      'class' => implode(' ', $classes),
      'title' => $title,
    );

    return javelin_tag(
      $uri ? 'a' : 'span',
      $attributes,
      array($icon, $name));
  }

  public function renderTag() {
    return id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setShade($this->getTagColor())
      ->setIcon($this->getIcon())
      ->setHref($this->getURI())
      ->setName($this->getLinkName());
  }

  public function getLinkName() {
    switch ($this->getType()) {
      case PhabricatorPeopleUserPHIDType::TYPECONST:
        $name = $this->getName();
        break;
      default:
        $name = $this->getFullName();
        break;
    }
    return $name;
  }

  protected function getPHIDType() {
    $types = PhabricatorPHIDType::getAllTypes();
    return idx($types, $this->getType());
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_PUBLIC;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    // NOTE: Handles are always visible, they just don't get populated with
    // data if the user can't see the underlying object.
    return true;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
