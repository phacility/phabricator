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

class DifferentialChangesetListView extends AphrontView {

  private $changesets = array();
  private $references = array();
  private $editable;
  private $revision;
  private $renderURI = '/differential/changeset/';
  private $whitespace;
  private $standaloneViews;
  private $user;
  private $symbolIndexes = array();
  private $repository;
  private $diff;
  private $vsMap;

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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
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

  public function setVsMap(array $vs_map) {
    $this->vsMap = $vs_map;
    return $this;
  }

  public function getVsMap() {
    return $this->vsMap;
  }

  public function render() {
    require_celerity_resource('differential-changeset-view-css');

    $changesets = $this->changesets;

    if ($this->standaloneViews) {
      Javelin::initBehavior(
        'differential-dropdown-menus',
        array());
    }

    $output = array();
    $mapping = array();
    $repository = $this->repository;
    foreach ($changesets as $key => $changeset) {
      $file = $changeset->getFilename();
      $class = 'differential-changeset';
      if (!$this->editable) {
        $class .= ' differential-changeset-noneditable';
      }

      $ref = $this->references[$key];

      $detail = new DifferentialChangesetDetailView();

      $detail_button = null;
      if ($this->standaloneViews) {
        $detail_uri = new PhutilURI($this->renderURI);
        $detail_uri->setQueryParams(array('ref' => $ref));

        $diffusion_uri = null;
        if ($repository) {
          $diffusion_uri = $repository->getDiffusionBrowseURIForPath(
            $changeset->getAbsoluteRepositoryPath($this->diff, $repository));
        }

        $meta = array(
          'detailURI'     =>
            (string)$detail_uri->alter('whitespace', $this->whitespace),
          'diffusionURI'  => $diffusion_uri,
          'containerID'   => $detail->getID(),
        );
        $change = $changeset->getChangeType();
        if ($change != DifferentialChangeType::TYPE_ADD) {
          $meta['leftURI'] = (string)$detail_uri->alter('view', 'old');
        }
        if ($change != DifferentialChangeType::TYPE_DELETE &&
            $change != DifferentialChangeType::TYPE_MULTICOPY) {
          $meta['rightURI'] = (string)$detail_uri->alter('view', 'new');
        }

        if ($this->user && $repository) {
          $path = ltrim(
            $changeset->getAbsoluteRepositoryPath($this->diff, $repository),
            '/');
          $line = 1; // TODO: get first changed line
          $editor_link = $this->user->loadEditorLink($path, $line, $repository);
          if ($editor_link) {
            $meta['editor'] = $editor_link;
          } else {
            $meta['editorConfigure'] = '/settings/page/preferences/';
          }
        }

        $detail_button = javelin_render_tag(
          'a',
          array(
            'class'   => 'button small grey',
            'meta'    => $meta,
            'href'    => $meta['detailURI'],
            'target'  => '_blank',
            'sigil'   => 'differential-view-options',
          ),
          "View Options \xE2\x96\xBC");
      }

      $detail->setChangeset($changeset);
      $detail->addButton($detail_button);
      $detail->setSymbolIndex(idx($this->symbolIndexes, $key));
      $detail->setVsChangesetID(idx($this->vsMap, $changeset->getID()));

      $uniq_id = celerity_generate_unique_node_id();
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
