<?php

final class AphrontFormToggleButtonsControl extends AphrontFormControl {

  private $baseURI;
  private $param;

  private $buttons;

  public function setBaseURI(PhutilURI $uri, $param) {
    $this->baseURI = $uri;
    $this->param = $param;
    return $this;
  }

  public function setButtons(array $buttons) {
    $this->buttons = $buttons;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-togglebuttons';
  }

  protected function renderInput() {
    if (!$this->baseURI) {
      throw new Exception('Call setBaseURI() before render()!');
    }

    $selected = $this->getValue();

    $out = array();
    foreach ($this->buttons as $value => $label) {
      if ($value == $selected) {
        $more = ' toggle-selected toggle-fixed';
      } else {
        $more = null;
      }

      $out[] = phutil_render_tag(
        'a',
        array(
          'class' => 'toggle'.$more,
          'href'  => $this->baseURI->alter($this->param, $value),
        ),
        phutil_escape_html($label));
    }

    return implode('', $out);
  }

}
