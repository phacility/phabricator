<?php

final class ConpherenceFormDragAndDropUploadControl extends AphrontFormControl {

  private $dropID;

  public function setDropID($drop_id) {
    $this->dropID = $drop_id;
    return $this;
  }
  public function getDropID() {
    return $this->dropID;
  }

  protected function getCustomControlClass() {
    return null;
  }

  protected function renderInput() {

    $drop_id = celerity_generate_unique_node_id();
    Javelin::initBehavior('conpherence-drag-and-drop-photo',
      array(
        'target' => $drop_id,
        'form_pane' => 'conpherence-form',
        'upload_uri' => '/file/dropupload/',
        'activated_class' => 'conpherence-dialogue-upload-photo',
      ));
    require_celerity_resource('conpherence-update-css');

    return phutil_tag(
      'div',
      array(
        'id'    => $drop_id,
        'class' => 'conpherence-dialogue-drag-photo',
      ),
      pht('Drag and drop an image here to upload it.'));
  }

}
