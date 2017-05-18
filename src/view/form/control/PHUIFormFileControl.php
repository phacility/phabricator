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


    // If the control has a value, add a hidden input which submits it as a
    // default. This allows the file control to mean "don't change anything",
    // instead of "remove the file", if the user submits the form without
    // touching it.

    // This also allows the input to "hold" the value of an uploaded file if
    // there is another error in the form: when you submit the form but are
    // stopped because of an unrelated error, submitting it again will keep
    // the value around (if you don't upload a new file) instead of requiring
    // you to pick the file again.

    // TODO: This works alright, but is a bit of a hack, and the UI should
    // provide the user better feedback about whether the state of the control
    // is "keep the value the same" or "remove the value", and about whether
    // or not the control is "holding" a value from a previous submission.

    $default_input = null;
    $default_value = $this->getValue();
    if ($default_value !== null) {
      $default_input = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $this->getName().'_default',
          'value' => $default_value,
        ));
    }

    return array(
      phutil_tag(
        'input',
        array(
          'type' => 'file',
          'multiple' => $this->getAllowMultiple() ? 'multiple' : null,
          'name' => $this->getName().'_raw',
          'id' => $file_id,
          'disabled' => $this->getDisabled() ? 'disabled' : null,
        )),
      $default_input,
    );
  }

}
