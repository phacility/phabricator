<?php

/**
 * @concrete-extensible
 */
class AphrontFormTextAreaControl extends AphrontFormControl {

  const HEIGHT_VERY_SHORT = 'very-short';
  const HEIGHT_SHORT      = 'short';
  const HEIGHT_VERY_TALL  = 'very-tall';

  private $height;
  private $readOnly;
  private $customClass;
  private $placeHolder;
  private $sigil;

  public function setSigil($sigil) {
    $this->sigil = $sigil;
    return $this;
  }

  public function getSigil() {
    return $this->sigil;
  }

  public function setPlaceHolder($place_holder) {
    $this->placeHolder = $place_holder;
    return $this;
  }
  private function getPlaceHolder() {
    return $this->placeHolder;
  }

  public function setHeight($height) {
    $this->height = $height;
    return $this;
  }

  public function setReadOnly($read_only) {
    $this->readOnly = $read_only;
    return $this;
  }

  protected function getReadOnly() {
    return $this->readOnly;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-textarea';
  }

  public function setCustomClass($custom_class) {
    $this->customClass = $custom_class;
    return $this;
  }

  protected function renderInput() {

    $height_class = null;
    switch ($this->height) {
      case self::HEIGHT_VERY_SHORT:
      case self::HEIGHT_SHORT:
      case self::HEIGHT_VERY_TALL:
        $height_class = 'aphront-textarea-'.$this->height;
        break;
    }

    $classes = array();
    $classes[] = $height_class;
    $classes[] = $this->customClass;
    $classes = trim(implode(' ', $classes));

    return javelin_tag(
      'textarea',
      array(
        'name'        => $this->getName(),
        'disabled'    => $this->getDisabled() ? 'disabled' : null,
        'readonly'    => $this->getReadonly() ? 'readonly' : null,
        'class'       => $classes,
        'style'       => $this->getControlStyle(),
        'id'          => $this->getID(),
        'sigil'       => $this->sigil,
        'placeholder' => $this->getPlaceHolder(),
      ),
      // NOTE: This needs to be string cast, because if we pass `null` the
      // tag will be self-closed and some browsers aren't thrilled about that.
      (string)$this->getValue());
  }

}
