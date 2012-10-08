<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
