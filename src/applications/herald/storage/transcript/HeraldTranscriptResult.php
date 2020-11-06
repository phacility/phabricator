<?php

abstract class HeraldTranscriptResult
  extends Phobject {

  private $resultCode;
  private $resultData = array();

  final protected function setResultCode($result_code) {
    $this->resultCode = $result_code;
    return $this;
  }

  final protected function loadFromResultMap(array $map) {
    $result_code = idx($map, 'code');
    $result_data = idx($map, 'data', array());

    $this
      ->setResultCode($result_code)
      ->setResultData($result_data);

    return $this;
  }

  final public function getResultCode() {
    return $this->resultCode;
  }

  final protected function getResultData() {
    return $this->resultData;
  }

  final public function setResultData(array $result_data) {
    $this->resultData = $result_data;
    return $this;
  }

  final public function getIconIcon() {
    return $this->getSpecificationProperty('icon');
  }

  final public function getIconColor() {
    return $this->getSpecificationProperty('color.icon');
  }

  final public function getName() {
    return $this->getSpecificationProperty('name');
  }

  abstract public function newDetailsView(PhabricatorUser $viewer);

  final protected function getDataProperty($key, $default = null) {
    $data = $this->getResultData();
    return idx($data, $key, $default);
  }

  final public function newResultMap() {
    return array(
      'code' => $this->getResultCode(),
      'data' => $this->getResultData(),
    );
  }

  final protected function getSpecificationProperty($key) {
    $map = $this->getResultSpecification($this->getResultCode());
    return $map[$key];
  }

  final protected function getResultSpecification($result_code) {
    $map = $this->newResultSpecificationMap();

    if (!isset($map[$result_code])) {
      throw new Exception(
        pht(
          'Result code "%s" is unknown.',
          $result_code));
    }

    return $map[$result_code];
  }

  abstract protected function newResultSpecificationMap();

  final protected function newErrorView($error_class, $error_message) {
    return pht(
      '%s: %s',
      phutil_tag('strong', array(), $error_class),
      phutil_escape_html_newlines($error_message));
  }

}
