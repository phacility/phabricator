<?php

final class DifferentialRepositoryFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->value = $this->getRevision()->getRepositoryPHID();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $value = head($request->getArr('repositoryPHID'));
    $this->value = nonempty($value, null);
    return $this;
  }


  public function getRequiredHandlePHIDsForRevisionEdit() {
    return array_filter(array($this->value));
  }

  public function renderEditControl() {
    $value = array();
    if ($this->value) {
      $value = array(
        $this->getHandle($this->value),
      );
    }

    return id(new AphrontFormTokenizerControl())
      ->setLabel('Repository')
      ->setName('repositoryPHID')
      ->setUser($this->getUser())
      ->setLimit(1)
      ->setDatasource('/typeahead/common/repositories/')
      ->setValue($value);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setRepositoryPHID($this->value);
  }

}
