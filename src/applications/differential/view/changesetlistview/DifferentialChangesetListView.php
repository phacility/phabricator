<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class DifferentialChangesetListView extends AphrontView {

  private $changesets = array();
  private $editable;
  private $revision;
  private $renderURI = '/differential/changeset/';
  private $vsMap = array();

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function setEditable($editable) {
    $this->editable = $editable;
    return $this;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setVsMap(array $vs_map) {
    $this->vsMap = $vs_map;
    return $this;
  }

  public function setRenderURI($render_uri) {
    $this->renderURI = $render_uri;
    return $this;
  }

  public function render() {
    require_celerity_resource('differential-changeset-view-css');

    $vs_map = $this->vsMap;
    $changesets = $this->changesets;

    $output = array();
    $mapping = array();
    foreach ($changesets as $key => $changeset) {
      $file = $changeset->getFilename();
      $class = 'differential-changeset';
      if (!$this->editable) {
        $class .= ' differential-changeset-noneditable';
      }
      $id = $changeset->getID();
      if ($id) {
        $vs_id = idx($vs_map, $id);
      } else {
        $vs_id = null;
      }

      $detail_uri = new PhutilURI('/differential/changeset/');
      $detail_uri->setQueryParams(
        array(
          'id'          => $id,
          'vs'          => $vs_id,
          'whitespace'  => 'TODO',
        ));

      $detail_button = phutil_render_tag(
        'a',
        array(
          'style'   => 'float: right',
          'class'   => 'button small grey',
          'href'    => $detail_uri,
          'target'  => '_blank',
        ),
        'Standalone View');

      $uniq_id = celerity_generate_unique_node_id();

      $detail = new DifferentialChangesetDetailView();
      $detail->setChangeset($changeset);
      $detail->addButton($detail_button);
      $detail->appendChild(
        phutil_render_tag(
          'div',
          array(
            'id' => $uniq_id,
          ),
          '<div class="differential-loading">Loading...</div>'));
      $output[] = $detail->render();

      $mapping[$uniq_id] = array(
        $changeset->getID(),
        $vs_id);
    }

    $whitespace = null;
    Javelin::initBehavior('differential-populate', array(
      'registry'    => $mapping,
      'whitespace'  => $whitespace,
      'uri'         => $this->renderURI,
    ));

    Javelin::initBehavior('differential-show-more', array(
      'uri' => $this->renderURI,
    ));

    if ($this->editable) {
      $revision = $this->revision;
      Javelin::initBehavior('differential-edit-inline-comments', array(
        'uri' => '/differential/comment/inline/edit/'.$revision->getID().'/',
      ));
    }

    return
      '<div class="differential-review-stage">'.
        implode("\n", $output).
      '</div>';
  }

}
