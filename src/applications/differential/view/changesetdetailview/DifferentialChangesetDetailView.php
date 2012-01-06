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

class DifferentialChangesetDetailView extends AphrontView {

  private $changeset;
  private $buttons = array();
  private $revisionID;
  private $symbolIndex;
  private $id;

  public function setChangeset($changeset) {
    $this->changeset = $changeset;
    return $this;
  }

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function setRevisionID($revision_id) {
    $this->revisionID = $revision_id;
    return $this;
  }

  public function setSymbolIndex($symbol_index) {
    $this->symbolIndex = $symbol_index;
    return $this;
  }

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function render() {
    require_celerity_resource('differential-changeset-view-css');
    require_celerity_resource('syntax-highlighting-css');

    if ($this->revisionID) {
      $edit = true;
    } else {
      $edit = false;
    }

    $changeset = $this->changeset;
    $class = 'differential-changeset';
    if (!$edit) {
      $class .= ' differential-changeset-immutable';
    }

    $buttons = null;
    if ($this->buttons) {
      $buttons =
        '<div class="differential-changeset-buttons">'.
          implode('', $this->buttons).
        '</div>';
    }

    $id = $this->getID();

    if ($this->symbolIndex) {
      Javelin::initBehavior(
        'repository-crossreference',
        array(
          'container' => $id,
        ) + $this->symbolIndex);
    }

    $display_filename = $changeset->getDisplayFilename();
    $output = javelin_render_tag(
      'div',
      array(
        'sigil' => 'differential-changeset',
        'meta'  => array(
          'left'  => $this->changeset->getID(),
          'right' => $this->changeset->getID(),
        ),
        'class' => $class,
        'id'    => $id,
      ),
      phutil_render_tag(
        'a',
        array(
          'name' => $changeset->getAnchorName(),
        ),
        '').
      $buttons.
      '<h1>'.phutil_escape_html($display_filename).'</h1>'.
      '<div style="clear: both;"></div>'.
      $this->renderChildren());

    return $output;
  }

}
