<?php

final class ReleephRequestTypeaheadControl extends AphrontFormControl {

  const PLACEHOLDER = 'Type a commit id or first line of commit message...';

  private $repo;
  private $startTime;

  public function setRepo(PhabricatorRepository $repo) {
    $this->repo = $repo;
    return $this;
  }

  public function setStartTime($epoch) {
    $this->startTime = $epoch;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'releeph-request-typeahead';
  }

  protected function renderInput() {
    $id = celerity_generate_unique_node_id();

    $div = phutil_tag(
      'div',
      array(
        'style' => 'position: relative;',
        'id' => $id,
      ),
      phutil_tag(
        'input',
        array(
          'autocomplete' => 'off',
          'type' => 'text',
          'name' => $this->getName(),
        ),
        ''));

    require_celerity_resource('releeph-request-typeahead-css');

    Javelin::initBehavior('releeph-request-typeahead', array(
      'id'  => $id,
      'src' => '/releeph/request/typeahead/',
      'placeholder' => self::PLACEHOLDER,
      'value'       => $this->getValue(),
      'aux' => array(
        'repo'      => $this->repo->getID(),
        'callsign'  => $this->repo->getCallsign(),
        'since'     => $this->startTime,
        'limit'     => 16,
      ),
    ));

    return $div;
  }

}
