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
  private $enableDragAndDropFileUploads;
  private $customClass;

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

    return phutil_render_tag(
      'textarea',
      array(
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'readonly'  => $this->getReadonly() ? 'readonly' : null,
        'class'     => $classes,
        'style'     => $this->getControlStyle(),
        'id'        => $this->getID(),
      ),
      phutil_escape_html($this->getValue()));
  }

}
