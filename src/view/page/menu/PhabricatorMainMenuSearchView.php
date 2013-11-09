<?php

final class PhabricatorMainMenuSearchView extends AphrontView {

  private $scope;
  private $id;

  public function setScope($scope) {
    $this->scope = $scope;
    return $this;
  }

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function render() {
    $user = $this->user;

    $target_id  = celerity_generate_unique_node_id();
    $search_id = $this->getID();

    $input = phutil_tag(
      'input',
      array(
        'type' => 'text',
        'name' => 'query',
        'id' => $search_id,
        'autocomplete' => 'off',
      ));

    $scope = $this->scope;

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
        'src'         => '/typeahead/common/mainsearch/',
        'limit'       => 10,
        'placeholder' => PhabricatorSearchScope::getScopePlaceholder($scope),
      ));

    $scope_input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'scope',
        'value' => $scope,
      ));

    $form = phabricator_form(
      $user,
      array(
        'action' => '/search/',
        'method' => 'POST',
      ),
      phutil_tag_div('phabricator-main-menu-search-container', array(
        $input,
        phutil_tag('button', array(), pht('Search')),
        $scope_input,
        $target,
      )));

    return $form;
  }

}
