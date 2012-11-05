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
        $buttons =
          '<div class="phabricator-object-list-view-buttons">'.
            implode('', $buttons).
          '</div>';
      } else {
        $buttons = null;
      }

      $out[] = javelin_render_tag(
        'div',
        array(
          'class' => 'phabricator-object-list-view-item',
          'style' => 'background-image: url('.$handle->getImageURI().');',
        ),
        $handle->renderLink().$buttons);
    }

    return
      '<div class="phabricator-object-list-view">'.
        implode("\n", $out).
      '</div>';
  }

}
