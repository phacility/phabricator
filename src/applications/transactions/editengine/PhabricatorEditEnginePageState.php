<?php

final class PhabricatorEditEnginePageState
  extends Phobject {

  private $isCreate;
  private $isSubmit;
  private $isError;
  private $isSave;

  public function setIsCreate($is_create) {
    $this->isCreate = $is_create;
    return $this;
  }

  public function getIsCreate() {
    return $this->isCreate;
  }

  public function setIsSubmit($is_submit) {
    $this->isSubmit = $is_submit;
    return $this;
  }

  public function getIsSubmit() {
    return $this->isSubmit;
  }

  public function setIsError($is_error) {
    $this->isError = $is_error;
    return $this;
  }

  public function getIsError() {
    return $this->isError;
  }

  public function setIsSave($is_save) {
    $this->isSave = $is_save;
    return $this;
  }

  public function getIsSave() {
    return $this->isSave;
  }

}
