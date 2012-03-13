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

final class AphrontFormTextAreaControl extends AphrontFormControl {

  const HEIGHT_VERY_SHORT = 'very-short';
  const HEIGHT_SHORT      = 'short';
  const HEIGHT_VERY_TALL  = 'very-tall';

  private $height;
  private $readOnly;
  private $enableDragAndDropFileUploads;


  public function setHeight($height) {
    $this->height = $height;
    return $this;
  }

  public function setReadOnly($read_only) {
    $this->readOnly = $read_only;
    return $this;
  }

  protected function getReadOnly() {
    return $this->readOnly;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-textarea';
  }

  public function setEnableDragAndDropFileUploads($enable) {
    $this->enableDragAndDropFileUploads = $enable;
    return $this;
  }

  protected function renderInput() {

    $height_class = null;
    switch ($this->height) {
      case self::HEIGHT_VERY_SHORT:
      case self::HEIGHT_SHORT:
      case self::HEIGHT_VERY_TALL:
        $height_class = 'aphront-textarea-'.$this->height;
        break;
    }

    $id = $this->getID();
    if ($this->enableDragAndDropFileUploads) {
      if (!$id) {
        $id = celerity_generate_unique_node_id();
      }
      Javelin::initBehavior(
        'aphront-drag-and-drop-textarea',
        array(
          'target'          => $id,
          'activatedClass'  => 'aphront-textarea-drag-and-drop',
          'uri'             => '/file/dropupload/',
        ));
    }

    return phutil_render_tag(
      'textarea',
      array(
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'readonly'  => $this->getReadonly() ? 'readonly' : null,
        'class'     => $height_class,
        'style'     => $this->getControlStyle(),
        'id'        => $id,
      ),
      phutil_escape_html($this->getValue()));
  }

}
