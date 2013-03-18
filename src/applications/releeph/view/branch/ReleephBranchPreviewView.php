<?php

final class ReleephBranchPreviewView extends AphrontFormControl {

  private $statics = array();
  private $dynamics = array();

  public function addControl($param_name, AphrontFormControl $control) {
    $celerity_id = celerity_generate_unique_node_id();
    $control->setID($celerity_id);
    $this->dynamics[$param_name] = $celerity_id;
    return $this;
  }

  public function addStatic($param_name, $value) {
    $this->statics[$param_name] = $value;
    return $this;
  }

  public function getCustomControlClass() {
    require_celerity_resource('releeph-preview-branch');
    return 'releeph-preview-branch';
  }

  public function renderInput() {
    static $required_params = array(
      'arcProjectID',
      'projectName',
      'isSymbolic',
      'template',
    );

    $all_params = array_merge($this->statics, $this->dynamics);
    foreach ($required_params as $param_name) {
      if (idx($all_params, $param_name) === null) {
        throw new Exception(
          "'{$param_name}' is not set as either a static or dynamic!");
      }
    }

    $output_id = celerity_generate_unique_node_id();

    Javelin::initBehavior('releeph-preview-branch', array(
      'uri'           => '/releeph/branch/preview/',
      'outputID'      => $output_id,
      'params'        => array(
        'static'  => $this->statics,
        'dynamic' => $this->dynamics,
      )
    ));

    return phutil_tag(
      'div',
      array(
        'id' => $output_id,
      ),
      '');
  }

}
