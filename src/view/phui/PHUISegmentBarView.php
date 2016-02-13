<?php

final class PHUISegmentBarView extends AphrontTagView {

  private $label;
  private $segments = array();

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function newSegment() {
    $segment = new PHUISegmentBarSegmentView();
    $this->segments[] = $segment;
    return $segment;
  }

  protected function canAppendChild() {
    return false;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-segment-bar-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-segment-bar-view-css');

    $label = $this->label;
    if (strlen($label)) {
      $label = phutil_tag(
        'div',
        array(
          'class' => 'phui-segment-bar-label',
        ),
        $label);
    }

    $segments = $this->segments;

    $position = 0;
    foreach ($segments as $segment) {
      $segment->setPosition($position);
      $position += $segment->getWidth();
    }

    $segments = array_reverse($segments);

    $segments = phutil_tag(
      'div',
      array(
        'class' => 'phui-segment-bar-segments',
      ),
      $segments);

    return array(
      $label,
      $segments,
    );
  }

}
