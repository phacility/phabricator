<?php

final class PhabricatorMainMenuSearchView extends AphrontView {

  const DEFAULT_APPLICATION_ICON = 'fa-dot-circle-o';

  private $id;
  private $application;

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function render() {
    $viewer = $this->getViewer();

    $target_id = celerity_generate_unique_node_id();
    $search_id = $this->getID();
    $button_id = celerity_generate_unique_node_id();
    $selector_id = celerity_generate_unique_node_id();
    $application_id = celerity_generate_unique_node_id();

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

    $search_datasource = new PhabricatorSearchDatasource();
    $scope_key = PhabricatorSearchScopeSetting::SETTINGKEY;

    Javelin::initBehavior(
      'phabricator-search-typeahead',
      array(
        'id' => $target_id,
        'input' => $search_id,
        'button' => $button_id,
        'selectorID' => $selector_id,
        'applicationID' => $application_id,
        'defaultApplicationIcon' => self::DEFAULT_APPLICATION_ICON,
        'appScope' => PhabricatorSearchController::SCOPE_CURRENT_APPLICATION,
        'src' => $search_datasource->getDatasourceURI(),
        'limit' => 10,
        'placeholder' => pht('Search'),
        'scopeUpdateURI' => '/settings/adjust/?key='.$scope_key,
      ));

    $primary_input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'search:primary',
        'value' => 'true',
      ));

    $search_text = javelin_tag(
      'span',
      array(
        'aural' => true,
      ),
      pht('Search'));

    $selector = $this->buildModeSelector($selector_id, $application_id);

    $form = phabricator_form(
      $viewer,
      array(
        'action' => '/search/',
        'method' => 'POST',
      ),
      phutil_tag_div('phabricator-main-menu-search-container', array(
        $input,
        phutil_tag(
          'button',
          array(
            'id' => $button_id,
            'class' => 'phui-icon-view phui-font-fa fa-search',
            ),
          array(
            $selector,
            $search_text,
          )),
        $primary_input,
        $target,
      )));

    return $form;
  }

  private function buildModeSelector($selector_id, $application_id) {
    $viewer = $this->getViewer();

    $items = array();
    $items[] = array(
      'name' => pht('Search'),
    );

    $items[] = array(
      'icon' => 'fa-globe',
      'name' => pht('All Documents'),
      'value' => 'all',
    );

    $application_value = null;
    $application_icon = self::DEFAULT_APPLICATION_ICON;
    $application = $this->getApplication();
    if ($application) {
      $application_value = get_class($application);
      if ($application->getApplicationSearchDocumentTypes()) {
        $application_icon = $application->getIcon();
      }
    }

    $items[] = array(
      'icon' => $application_icon,
      'name' => pht('Current Application'),
      'value' => PhabricatorSearchController::SCOPE_CURRENT_APPLICATION,
    );

    $items[] = array(
      'name' => pht('Saved Queries'),
    );


    $engine = id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer($viewer);
    $engine_queries = $engine->loadEnabledNamedQueries();
    $query_map = mpull($engine_queries, 'getQueryName', 'getQueryKey');
    foreach ($query_map as $query_key => $query_name) {
      if ($query_key == 'all') {
        // Skip the builtin "All" query since it's redundant with the default
        // setting.
        continue;
      }

      $items[] = array(
        'icon' => 'fa-certificate',
        'name' => $query_name,
        'value' => $query_key,
      );
    }

    $items[] =  array(
      'name' => pht('More Options'),
    );

    $items[] = array(
      'icon' => 'fa-search-plus',
      'name' => pht('Advanced Search'),
      'href' => '/search/query/advanced/',
    );

    $items[] = array(
      'icon' => 'fa-book',
      'name' => pht('User Guide: Search'),
      'href' => PhabricatorEnv::getDoclink('Search User Guide'),
    );

    $scope_key = PhabricatorSearchScopeSetting::SETTINGKEY;
    $current_value = $viewer->getUserSetting($scope_key);

    $current_icon = 'fa-globe';
    foreach ($items as $item) {
      if (idx($item, 'value') == $current_value) {
        $current_icon = $item['icon'];
        break;
      }
    }

    $selector = id(new PHUIButtonView())
      ->setID($selector_id)
      ->addClass('phabricator-main-menu-search-dropdown')
      ->addSigil('global-search-dropdown')
      ->setMetadata(
        array(
          'items' => $items,
          'icon' => $current_icon,
          'value' => $current_value,
        ))
      ->setIcon(
        id(new PHUIIconView())
          ->addSigil('global-search-dropdown-icon')
          ->setIcon($current_icon))
      ->setDropdown(true);

    $input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'sigil' => 'global-search-dropdown-input',
        'name' => 'search:scope',
        'value' => $current_value,
      ));

    $application_input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'id' => $application_id,
        'sigil' => 'global-search-dropdown-app',
        'name' => 'search:application',
        'value' => $application_value,
      ));

    return array($selector, $input, $application_input);
  }

}
