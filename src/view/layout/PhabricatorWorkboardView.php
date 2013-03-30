<?php

final class PhabricatorWorkboardView extends AphrontView {

  private $panels = array();
  private $flexLayout = false;

  public function addPanel(PhabricatorWorkpanelView $panel) {
    $this->panels[] = $panel;
    return $this;
  }

  public function setFlexLayout($layout) {
    $this->flexLayout = $layout;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-workboard-view-css');

    $classes = array();
    $classes[] = 'phabricator-workboard-view-inner';

    if (count($this->panels) > 6) {
      throw new Exception("No more than 6 panels per workboard.");
    }

    $classes[] = 'workboard-'.count($this->panels).'-up';

    $view = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $this->panels,
      ));

    $classes = array();
    $classes[] = 'phabricator-workboard-view-outer';
    if ($this->flexLayout) {
      $classes[] = 'phabricator-workboard-flex';
    } else {
      $classes[] = 'phabricator-workboard-fixed';
    }

    return phutil_tag(
      'div',
        array(
          'class' => implode(' ', $classes)
        ),
        $view);
  }
}
