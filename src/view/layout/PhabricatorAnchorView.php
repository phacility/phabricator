<?php

final class PhabricatorAnchorView extends AphrontView {

  private $anchorName;
  private $navigationMarker;

  public function setAnchorName($name) {
    $this->anchorName = $name;
    return $this;
  }

  public function setNavigationMarker($marker) {
    $this->navigationMarker = $marker;
    return $this;
  }

  public function render() {
    $marker = null;
    if ($this->navigationMarker) {
      $marker = javelin_render_tag(
        'legend',
        array(
          'class' => 'phabricator-anchor-navigation-marker',
          'sigil' => 'marker',
          'meta'  => array(
            'anchor' => $this->anchorName,
          ),
        ),
        '');
    }

    $anchor = phutil_render_tag(
      'a',
      array(
        'name'  => $this->anchorName,
        'id'    => $this->anchorName,
        'class' => 'phabricator-anchor-view',
      ),
      '');

    return $marker.$anchor;
  }

}
