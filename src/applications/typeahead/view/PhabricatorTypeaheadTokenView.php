<?php

final class PhabricatorTypeaheadTokenView
  extends AphrontTagView {

  const TYPE_OBJECT = 'object';
  const TYPE_DISABLED = 'disabled';
  const TYPE_FUNCTION = 'function';
  const TYPE_INVALID = 'invalid';

  private $key;
  private $icon;
  private $color;
  private $inputName;
  private $value;
  private $tokenType = self::TYPE_OBJECT;
  private $availabilityColor;

  public static function newFromTypeaheadResult(
    PhabricatorTypeaheadResult $result) {

    return id(new PhabricatorTypeaheadTokenView())
      ->setKey($result->getPHID())
      ->setIcon($result->getIcon())
      ->setColor($result->getColor())
      ->setValue($result->getDisplayName())
      ->setTokenType($result->getTokenType());
  }

  public static function newFromHandle(
    PhabricatorObjectHandle $handle) {

    $token = id(new PhabricatorTypeaheadTokenView())
      ->setKey($handle->getPHID())
      ->setValue($handle->getFullName())
      ->setIcon($handle->getTokenIcon());

    if ($handle->isDisabled() ||
        $handle->getStatus() == PhabricatorObjectHandle::STATUS_CLOSED) {
      $token->setTokenType(self::TYPE_DISABLED);
    } else {
      $token->setColor($handle->getTagColor());
    }

    $availability = $handle->getAvailability();
    $color = null;
    switch ($availability) {
      case PhabricatorObjectHandle::AVAILABILITY_PARTIAL:
        $color = PHUITagView::COLOR_ORANGE;
        break;
      case PhabricatorObjectHandle::AVAILABILITY_NONE:
        $color = PHUITagView::COLOR_RED;
        break;
    }

    if ($color !== null) {
      $token->setAvailabilityColor($color);
    }

    return $token;
  }

  public function isInvalid() {
    return ($this->getTokenType() == self::TYPE_INVALID);
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setTokenType($token_type) {
    $this->tokenType = $token_type;
    return $this;
  }

  public function getTokenType() {
    return $this->tokenType;
  }

  public function setInputName($input_name) {
    $this->inputName = $input_name;
    return $this;
  }

  public function getInputName() {
    return $this->inputName;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  protected function getTagName() {
    return 'a';
  }

  public function setAvailabilityColor($availability_color) {
    $this->availabilityColor = $availability_color;
    return $this;
  }

  public function getAvailabilityColor() {
    return $this->availabilityColor;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'jx-tokenizer-token';
    switch ($this->getTokenType()) {
      case self::TYPE_FUNCTION:
        $classes[] = 'jx-tokenizer-token-function';
        break;
      case self::TYPE_INVALID:
        $classes[] = 'jx-tokenizer-token-invalid';
        break;
      case self::TYPE_DISABLED:
        $classes[] = 'jx-tokenizer-token-disabled';
        break;
      case self::TYPE_OBJECT:
      default:
        break;
    }

    $classes[] = $this->getColor();

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    $input_name = $this->getInputName();
    if ($input_name) {
      $input_name .= '[]';
    }

    $value = $this->getValue();

    $availability = null;
    $availability_color = $this->getAvailabilityColor();
    if ($availability_color) {
      $availability = phutil_tag(
        'span',
        array(
          'class' => 'phui-tag-dot phui-tag-color-'.$availability_color,
        ));
    }

    $icon_view = null;
    $icon = $this->getIcon();
    if ($icon) {
      $icon_view = phutil_tag(
        'span',
        array(
          'class' => 'phui-icon-view phui-font-fa '.$icon,
        ));
    }

    return array(
      array(
        $icon_view,
        $availability,
        $value,
      ),
      phutil_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $input_name,
          'value' => $this->getKey(),
        )),
      phutil_tag('span', array('class' => 'jx-tokenizer-x-placeholder'), ''),
    );
  }

}
