<?php

final class PhabricatorWorkboardActionView extends AphrontView {

  private $href;
  private $workflow;
  private $image;

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
  }

  public function render() {

    return phutil_tag(
      'a',
        array(
          'href'  => $this->href,
          'class' => 'phabricator-workboard-action-item-link',
          'sigil' => $this->workflow ? 'workflow' : null,
          'style' => 'background-image: url('.$this->image.');'
        ),
        '');
  }
}
