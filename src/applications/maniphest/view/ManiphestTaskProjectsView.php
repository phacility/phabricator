<?php

/**
 * @group maniphest
 */
final class ManiphestTaskProjectsView extends ManiphestView {

  private $handles;

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-project-tag-css');


    $show = array_slice($this->handles, 0, 2);

    $tags = array();
    foreach ($show as $handle) {
      $tags[] = phutil_tag(
        'a',
        array(
          'href'  => $handle->getURI(),
          'class' => 'phabricator-project-tag',
        ),
        phutil_utf8_shorten($handle->getName(), 24));
    }

    if (count($this->handles) > 2) {
      require_celerity_resource('aphront-tooltip-css');
      Javelin::initBehavior('phabricator-tooltips');

      $all = array();
      foreach ($this->handles as $handle) {
        $all[] = $handle->getName();
      }

      $tags[] = javelin_tag(
        'span',
        array(
          'class' => 'phabricator-project-tag',
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip' => implode(', ', $all),
            'size' => 200,
          ),
        ),
        "\xE2\x80\xA6");
    }

    return $tags;
  }

}
