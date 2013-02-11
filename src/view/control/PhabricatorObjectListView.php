<?php

final class PhabricatorObjectListView extends AphrontView {

  private $handles = array();
  private $buttons = array();

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    $this->handles = $handles;
    return $this;
  }

  public function addButton(PhabricatorObjectHandle $handle, $button) {
    $this->buttons[$handle->getPHID()][] = $button;
    return $this;
  }

  public function render() {
    $handles = $this->handles;

    require_celerity_resource('phabricator-object-list-view-css');

    $out = array();
    foreach ($handles as $handle) {
      $buttons = idx($this->buttons, $handle->getPHID(), array());
      if ($buttons) {
        $buttons = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-object-list-view-buttons',
          ),
          $buttons);
      } else {
        $buttons = null;
      }

      $out[] = javelin_tag(
        'div',
        array(
          'class' => 'phabricator-object-list-view-item',
          'style' => 'background-image: url('.$handle->getImageURI().');',
        ),
        array(
          $handle->renderLink(),
          $buttons,
        ));
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-object-list-view',
      ),
      $out);
  }

}
