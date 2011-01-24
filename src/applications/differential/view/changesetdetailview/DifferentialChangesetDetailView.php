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

class DifferentialChangesetDetailView extends AphrontView {

  private $changesets = array();

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function render() {
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
      $detail_uri = URI($render_uri)
        ->addQueryData(array(
          'changeset'   => $id,
          'against'     => $against_id,
          'whitespace'  => $whitespace,
        ));
*/
      $detail_uri = '/differential/changeset/'.$changeset->getID().'/';

      $detail = phutil_render_tag(
        'a',
        array(
          'style'   => 'float: right',
          'class'   => 'button small grey',
          'href'    => $detail_uri,
          'target'  => '_blank',
        ),
        'Standalone View');

//      $div = <div class="differential-loading">Loading&hellip;</div>;

      $display_filename = $changeset->getDisplayFilename();
      $output[] =
        '<div>'.
          '<h1>'.$detail.phutil_escape_html($display_filename).'</h1>'.
          '<div>Loading...</div>'.
        '</div>';

/*
        <div class={$class}
             sigil="differential-changeset"
              meta={$changeset->getID()}>
          {$detail}
          <a name={alite_urlize($file)} /><h1>{$file}</h1>
          {$div}
        </div>;
*/
/*
      $mapping[$div->requireUniqueId()] = array_filter(
        array(
          $changeset->getID(),
          idx($against, $changeset->getID()),
        ));

*/
    }
/*
    require_static('differential-diff-css');
    require_static('differential-syntax-css');

    Javelin::initBehavior('differential-populate', array(
      'registry'    => $mapping,
      'whitespace'  => $whitespace,
      'uri'         => $render_uri,
    ));

    Javelin::initBehavior('differential-context', array(
      'uri' => $render_uri,
    ));

    if ($edit) {
      require_static('remarkup-css');
      Javelin::initBehavior('differential-inline', array(
        'uri'       => '/differential/feedback/'.$revision->getID().'/',
      ));
    }
*/
    return
      '<div class="differential-review-stage">'.
        implode("\n", $output).
      '</div>';
  }

}
