<?php

final class PhabricatorObjectSelectorDialog {

  private $user;
  private $filters = array();
  private $handles = array();
  private $cancelURI;
  private $submitURI;
  private $searchURI;
  private $selectedFilter;
  private $excluded;

  private $title;
  private $header;
  private $buttonText;
  private $instructions;

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function setFilters(array $filters) {
    $this->filters = $filters;
    return $this;
  }

  public function setSelectedFilter($selected_filter) {
    $this->selectedFilter = $selected_filter;
    return $this;
  }

  public function setExcluded($excluded_phid) {
    $this->excluded = $excluded_phid;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function setSubmitURI($submit_uri) {
    $this->submitURI = $submit_uri;
    return $this;
  }

  public function setSearchURI($search_uri) {
    $this->searchURI = $search_uri;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setButtonText($button_text) {
    $this->buttonText = $button_text;
    return $this;
  }

  public function setInstructions($instructions) {
    $this->instructions = $instructions;
    return $this;
  }

  public function buildDialog() {
    $user = $this->user;

    $filter_id = celerity_generate_unique_node_id();
    $query_id = celerity_generate_unique_node_id();
    $results_id = celerity_generate_unique_node_id();
    $current_id = celerity_generate_unique_node_id();
    $search_id  = celerity_generate_unique_node_id();
    $form_id = celerity_generate_unique_node_id();

    require_celerity_resource('phabricator-object-selector-css');

    $options = array();
    foreach ($this->filters as $key => $label) {
      $options[] = phutil_render_tag(
        'option',
        array(
          'value' => $key,
          'selected' => ($key == $this->selectedFilter)
            ? 'selected'
            : null,
        ),
        $label);
    }
    $options = implode("\n", $options);

    $instructions = null;
    if ($this->instructions) {
      $instructions =
        '<p class="phabricator-object-selector-instructions">'.
          $this->instructions.
        '</p>';
    }

    $search_box = phabricator_render_form(
      $user,
      array(
        'method' => 'POST',
        'action' => $this->submitURI,
        'id'     => $search_id,
      ),
      '<table class="phabricator-object-selector-search">
        <tr>
          <td class="phabricator-object-selector-search-filter">
            <select id="'.$filter_id.'">'.
              $options.
            '</select>
          </td>
          <td class="phabricator-object-selector-search-text">
            <input type="text" id="'.$query_id.'" />
          </td>
        </tr>
      </table>');
    $result_box =
      '<div class="phabricator-object-selector-results" id="'.$results_id.'">'.
      '</div>';
    $attached_box =
      '<div class="phabricator-object-selector-current">'.
        '<div class="phabricator-object-selector-currently-attached">'.
          '<div class="phabricator-object-selector-header">'.
            phutil_escape_html($this->header).
          '</div>'.
          '<div id="'.$current_id.'">'.
          '</div>'.
          $instructions.
        '</div>'.
      '</div>';


    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($this->user)
      ->setTitle($this->title)
      ->setClass('phabricator-object-selector-dialog')
      ->appendChild($search_box)
      ->appendChild($result_box)
      ->appendChild($attached_box)
      ->setRenderDialogAsDiv()
      ->setFormID($form_id)
      ->addSubmitButton($this->buttonText);

    if ($this->cancelURI) {
      $dialog->addCancelButton($this->cancelURI);
    }

    $handle_views = array();
    foreach ($this->handles as $handle) {
      $phid = $handle->getPHID();
      $view = new PhabricatorHandleObjectSelectorDataView($handle);
      $handle_views[$phid] = $view->renderData();
    }
    $dialog->addHiddenInput('phids', implode(';', array_keys($this->handles)));


    Javelin::initBehavior(
      'phabricator-object-selector',
      array(
        'filter'  => $filter_id,
        'query'   => $query_id,
        'search'  => $search_id,
        'results' => $results_id,
        'current' => $current_id,
        'form'    => $form_id,
        'exclude' => $this->excluded,
        'uri'     => $this->searchURI,
        'handles' => $handle_views,
      ));

   return $dialog;
  }

}
