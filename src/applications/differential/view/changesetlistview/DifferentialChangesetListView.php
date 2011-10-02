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
  private $whitespace;
  private $standaloneViews;
  private $symbolIndexes = array();

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function setEditable($editable) {
    $this->editable = $editable;
    return $this;
  }

  public function setStandaloneViews($has_standalone_views) {
    $this->standaloneViews = $has_standalone_views;
    return $this;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setRenderingReferences(array $references) {
    $this->references = $references;
    return $this;
  }

  public function setSymbolIndexes(array $indexes) {
    $this->symbolIndexes = $indexes;
    return $this;
  }

  public function setRenderURI($render_uri) {
    $this->renderURI = $render_uri;
    return $this;
  }

  public function setWhitespace($whitespace) {
    $this->whitespace = $whitespace;
    return $this;
  }

  public function render() {
    require_celerity_resource('differential-changeset-view-css');

    $changesets = $this->changesets;

    $output = array();
    $mapping = array();
    foreach ($changesets as $key => $changeset) {
      $file = $changeset->getFilename();
      $class = 'differential-changeset';
      if (!$this->editable) {
        $class .= ' differential-changeset-noneditable';
      }

      $ref = $this->references[$key];

      $detail_button = null;
      if ($this->standaloneViews) {
        $detail_uri = new PhutilURI($this->renderURI);
        $detail_uri->setQueryParams(
          array(
            'ref'         => $ref,
            'whitespace'  => $this->whitespace,
          ));

        $detail_button = phutil_render_tag(
          'a',
          array(
            'class'   => 'button small grey',
            'href'    => $detail_uri,
            'target'  => '_blank',
          ),
          'View Standalone / Raw');
      }

      $uniq_id = celerity_generate_unique_node_id();

      $detail = new DifferentialChangesetDetailView();
      $detail->setChangeset($changeset);
      $detail->addButton($detail_button);
      $detail->setSymbolIndex(idx($this->symbolIndexes, $key));
      $detail->appendChild(
        phutil_render_tag(
          'div',
          array(
            'id' => $uniq_id,
          ),
          '<div class="differential-loading">Loading...</div>'));
      $output[] = $detail->render();

      $mapping[$uniq_id] = $ref;
    }

    Javelin::initBehavior('differential-populate', array(
      'registry'    => $mapping,
      'whitespace'  => $this->whitespace,
      'uri'         => $this->renderURI,
    ));

    Javelin::initBehavior('differential-show-more', array(
      'uri' => $this->renderURI,
      'whitespace' => $this->whitespace,
    ));

    Javelin::initBehavior('differential-comment-jump', array());

    if ($this->editable) {

      $undo_templates = $this->renderUndoTemplates();

      $revision = $this->revision;
      Javelin::initBehavior('differential-edit-inline-comments', array(
        'uri' => '/differential/comment/inline/edit/'.$revision->getID().'/',
        'undo_templates' => $undo_templates,
      ));
    }

    return
      '<div class="differential-review-stage" id="differential-review-stage">'.
        implode("\n", $output).
      '</div>';
  }

  /**
   * Render the "Undo" markup for the inline comment undo feature.
   */
  private function renderUndoTemplates() {
    $link = javelin_render_tag(
      'a',
      array(
        'href'  => '#',
        'sigil' => 'differential-inline-comment-undo',
      ),
      'Undo');

    $div = phutil_render_tag(
      'div',
      array(
        'class' => 'differential-inline-undo',
      ),
      'Changes discarded. '.$link);

    $content = '<th></th><td>'.$div.'</td>';
    $empty   = '<th></th><td></td>';

    $left = array($content, $empty);
    $right = array($empty, $content);

    return array(
      'l' => '<table><tr>'.implode('', $left).'</tr></table>',
      'r' => '<table><tr>'.implode('', $right).'</tr></table>',
    );
  }

}
