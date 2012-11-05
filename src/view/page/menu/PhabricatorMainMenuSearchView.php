<?php

final class PhabricatorMainMenuSearchView extends AphrontView {

  private $user;
  private $scope;
  private $id;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

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

    $input = phutil_render_tag(
      'input',
      array(
        'type' => 'text',
        'name' => 'query',
        'id' => $search_id,
        'autocomplete' => 'off',
      ));

    $scope = $this->scope;

    $target = javelin_render_tag(
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

    $scope_input = phutil_render_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'scope',
        'value' => $scope,
      ));

    $form = phabricator_render_form(
      $user,
      array(
        'action' => '/search/',
        'method' => 'POST',
      ),
      '<div class="phabricator-main-menu-search-container">'.
        $input.
        '<button>Search</button>'.
        $scope_input.
        $target.
      '</div>');

    $group = new PhabricatorMainMenuGroupView();
    $group->addClass('phabricator-main-menu-search');
    $group->appendChild($form);
    return $group->render();
  }

}
