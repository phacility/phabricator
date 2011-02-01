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

  public function render() {
    require_celerity_resource('differential-changeset-view-css');

    $against = array(); // TODO
    $edit    = false;

    $changesets = $this->changesets;
    foreach ($changesets as $key => $changeset) {
      if (empty($against[$changeset->getID()])) {
        $type = $changeset->getChangeType();
        if ($type == DifferentialChangeType::TYPE_MOVE_AWAY ||
            $type == DifferentialChangeType::TYPE_MULTICOPY) {
          unset($changesets[$key]);
        }
      }
    }

    $output = array();
    $mapping = array();
    foreach ($changesets as $key => $changeset) {
      $file = $changeset->getFilename();
      $class = 'differential-changeset';
      if (!$edit) {
        $class .= ' differential-changeset-noneditable';
      }
      $id = $changeset->getID();
      if ($id) {
        $against_id = idx($against, $id);
      } else {
        $against_id = null;
      }

/*
      TODO
      $detail_uri = URI($render_uri)
        ->addQueryData(array(
          'changeset'   => $id,
          'against'     => $against_id,
          'whitespace'  => $whitespace,
        ));
*/
      $detail_uri = '/differential/changeset/?id='.$changeset->getID();

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

      $mapping[$uniq_id] = array($changeset->getID());
    }

    $whitespace = null;
    Javelin::initBehavior('differential-populate', array(
      'registry'    => $mapping,
      'whitespace'  => $whitespace,
      'uri'         => '/differential/changeset/',
    ));

    Javelin::initBehavior('differential-show-more', array(
      'uri' => '/differential/changeset/',
    ));

    if ($this->editable) {
      $revision = $this->revision;
      Javelin::initBehavior('differential-edit-inline-comments', array(
        'uri' => '/differential/inline/edit/'.$revision->getID().'/',
      ));
    }

    return
      '<div class="differential-review-stage">'.
        implode("\n", $output).
      '</div>';
  }

}
