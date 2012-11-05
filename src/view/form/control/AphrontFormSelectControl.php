<?php

final class AphrontFormSelectControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-select';
  }

  private $options;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function renderInput() {
    return self::renderSelectTag(
      $this->getValue(),
      $this->getOptions(),
      array(
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'id'        => $this->getID(),
      ));
  }

  public static function renderSelectTag(
    $selected,
    array $options,
    array $attrs = array()) {

    $option_tags = self::renderOptions($selected, $options);

    return javelin_render_tag(
      'select',
      $attrs,
      implode("\n", $option_tags));
  }

  private static function renderOptions($selected, array $options) {
    $tags = array();
    foreach ($options as $value => $thing) {
      if (is_array($thing)) {
        $tags[] = phutil_render_tag(
          'optgroup',
          array(
            'label' => $value,
          ),
          implode("\n", self::renderOptions($selected, $thing)));
      } else {
        $tags[] = phutil_render_tag(
          'option',
          array(
            'selected' => ($value == $selected) ? 'selected' : null,
            'value'    => $value,
          ),
          phutil_escape_html($thing));
      }
    }
    return $tags;
  }

}
