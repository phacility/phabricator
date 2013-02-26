<?php

final class AphrontFormCountedToggleButtonsControl extends AphrontFormControl {

  private $baseURI;
  private $param;

  private $buttons;
  private $counters = array();

  public function setBaseURI(PhutilURI $uri, $param) {
    $this->baseURI = $uri;
    $this->param = $param;
    return $this;
  }

  public function setButtons(array $buttons) {
    $this->buttons = $buttons;
    return $this;
  }

  public function setCounters(array $counters) {
    $this->counters = $counters;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-counted-togglebuttons';
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

      $counter = idx($this->counters, $value);

      if ($counter > 0) {
        $href = $this->baseURI->alter($this->param, $value);
        $counter_markup = phutil_tag(
          'div',
          array(
            'class' => 'counter',
          ),
          $counter);
      } else {
        $href = null;
        $counter_markup = '';
        $more .= ' disabled';
      }

      $attributes = array(
        'class' => 'toggle'.$more,
      );
      if ($href) {
        $attributes['href'] = $href;
      }

      $out[] = phutil_tag(
        'a',
        $attributes,
        array(
          $counter_markup,
          $label));
    }

    return $out;
  }

}
