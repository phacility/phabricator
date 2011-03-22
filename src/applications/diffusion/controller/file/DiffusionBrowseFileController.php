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

class DiffusionBrowseFileController extends DiffusionController {

  public function processRequest() {

    $content = array();
    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    $select_map = array(
      //'highlighted' => 'View as Highlighted Text',
      //'blame' => 'View as Highlighted Text with Blame',
      'plain' => 'View as Plain Text',
      //'plainblame' => 'View as Plain Text with Blame',
    );
    $selected = $this->getRequest()->getStr('view');
    $select = '<select name="view">';
    foreach ($select_map as $k => $v) {
      $option = phutil_render_tag(
        'option',
        array(
          'value' => $k,
          'selected' => ($k == $selected) ? 'selected' : null,
        ),
        phutil_escape_html($v));

      $select .= $option;
    }
    $select .= '</select>';

    if ($selected == 'plain') {
      $style =
        "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
    } else {
      // default style.
      $style =
        "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
    }

    // TODO: blame, color, line numbers, highlighting, etc etc

    $view_form = phutil_render_tag(
      'form',
      array(
        'action' => $this->getRequest()->getRequestURI(),
        'method' => 'get',
        'style'  => 'display: inline',
      ),
      $select.
      '<button>view</button>');

    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $this->diffusionRequest);
    $file_content = $file_query->loadFileContent();

    $corpus = phutil_render_tag(
      'textarea',
      array(
        'style' => $style,
      ),
      phutil_escape_html($file_content->getCorpus()));

    $content[] = $view_form;
    $content[] = $corpus;

    $nav = $this->buildSideNav('browse', true);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Browse',
      ));
  }
}
