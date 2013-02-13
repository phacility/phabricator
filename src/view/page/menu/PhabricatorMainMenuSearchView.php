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
      hsprintf(
        '<div class="phabricator-main-menu-search-container">'.
          '%s<button>Search</button>%s%s'.
        '</div>',
        $input,
        $scope_input,
        $target));

    return $form;
  }

}
