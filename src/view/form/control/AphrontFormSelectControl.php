<?php

final class AphrontFormSelectControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-select';
  }

  private $options;
  private $disabledOptions = array();

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function setDisabledOptions(array $disabled) {
    $this->disabledOptions = $disabled;
    return $this;
  }

  protected function renderInput() {
    return self::renderSelectTag(
      $this->getValue(),
      $this->getOptions(),
      array(
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'id'        => $this->getID(),
      ),
      $this->disabledOptions);
  }

  public static function renderSelectTag(
    $selected,
    array $options,
    array $attrs = array(),
    array $disabled = array()) {

    $option_tags = self::renderOptions($selected, $options, $disabled);

    return javelin_tag(
      'select',
      $attrs,
      $option_tags);
  }

  private static function renderOptions(
    $selected,
    array $options,
    array $disabled = array()) {
    $disabled = array_fuse($disabled);

    $tags = array();
    foreach ($options as $value => $thing) {
      if (is_array($thing)) {
        $tags[] = phutil_tag(
          'optgroup',
          array(
            'label' => $value,
          ),
          self::renderOptions($selected, $thing));
      } else {
        $tags[] = phutil_tag(
          'option',
          array(
            'selected' => ($value == $selected) ? 'selected' : null,
            'value'    => $value,
            'disabled' => isset($disabled[$value]) ? 'disabled' : null,
          ),
          $thing);
      }
    }
    return $tags;
  }

}
