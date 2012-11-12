<?php

final class AphrontFormDragAndDropUploadControl extends AphrontFormControl {

  private $activatedClass;

  public function __construct() {
    $this->setControlID(celerity_generate_unique_node_id());
    $this->setControlStyle('display: none;');
  }

  protected function getCustomControlClass() {
    return 'aphront-form-drag-and-drop-upload';
  }

  public function setActivatedClass($class) {
    $this->activatedClass = $class;
    return $this;
  }

  protected function renderInput() {
    require_celerity_resource('aphront-attached-file-view-css');
    $list_id = celerity_generate_unique_node_id();

    $files = $this->getValue();
    $value = array();
    if ($files) {
      foreach ($files as $file) {
        $view = new AphrontAttachedFileView();
        $view->setFile($file);
        $value[$file->getPHID()] = array(
          'phid' => $file->getPHID(),
          'html' => $view->render(),
        );
      }
    }

    Javelin::initBehavior(
      'aphront-drag-and-drop',
      array(
        'control'         => $this->getControlID(),
        'name'            => $this->getName(),
        'value'           => nonempty($value, null),
        'list'            => $list_id,
        'uri'             => '/file/dropupload/',
        'activatedClass'  => $this->activatedClass,
      ));

    return phutil_render_tag(
      'div',
      array(
        'id'    => $list_id,
        'class' => 'aphront-form-drag-and-drop-file-list',
      ),
      '');
  }

}
