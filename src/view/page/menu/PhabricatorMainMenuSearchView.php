<?php

final class PhabricatorMainMenuSearchView extends AphrontView {

  private $id;

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function render() {
    $user = $this->user;

    $target_id = celerity_generate_unique_node_id();
    $search_id = $this->getID();
    $button_id = celerity_generate_unique_node_id();

    $input = phutil_tag(
      'input',
      array(
        'type' => 'text',
        'name' => 'query',
        'id' => $search_id,
        'autocomplete' => 'off',
      ));

    $target = javelin_tag(
      'div',
      array(
        'id'    => $target_id,
        'class' => 'phabricator-main-menu-search-target',
      ),
      '');

    Javelin::initBehavior(
      'phabricator-search-typeahead',
      array(
        'id'          => $target_id,
        'input'       => $search_id,
        'button'      => $button_id,
        'src'         => '/typeahead/common/mainsearch/',
        'limit'       => 10,
        'placeholder' => pht('Search'),
      ));

    $primary_input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'search:primary',
        'value' => 'true',
      ));

    $form = phabricator_form(
      $user,
      array(
        'action' => '/search/',
        'method' => 'POST',
      ),
      phutil_tag_div('phabricator-main-menu-search-container', array(
        $input,
        phutil_tag(
          'button',
          array('id' => $button_id),
          pht('Search')),
        $primary_input,
        $target,
      )));

    return $form;
  }

}
