<?php

final class PHUIFormFileControl
  extends AphrontFormControl {

  private $allowMultiple;

  protected function getCustomControlClass() {
    return 'phui-form-file-upload';
  }

  public function setAllowMultiple($allow_multiple) {
    $this->allowMultiple = $allow_multiple;
    return $this;
  }

  public function getAllowMultiple() {
    return $this->allowMultiple;
  }

  protected function renderInput() {
    $file_id = $this->getID();

    Javelin::initBehavior(
      'phui-file-upload',
      array(
        'fileInputID' => $file_id,
        'inputName' => $this->getName(),
        'uploadURI' => '/file/dropupload/',
        'chunkThreshold' => PhabricatorFileStorageEngine::getChunkThreshold(),
      ));

    return phutil_tag(
      'input',
      array(
        'type' => 'file',
        'multiple' => $this->getAllowMultiple() ? 'multiple' : null,
        'name' => $this->getName().'.raw',
        'id' => $file_id,
        'disabled' => $this->getDisabled() ? 'disabled' : null,
      ));
  }

}
