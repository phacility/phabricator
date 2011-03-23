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

    // Build the view selection form.
    $select_map = array(
      'highlighted' => 'View as Highlighted Text',
      //'blame' => 'View as Highlighted Text with Blame',
      'plain' => 'View as Plain Text',
      //'plainblame' => 'View as Plain Text with Blame',
    );

    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();

    $selected = $request->getStr('view');
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

    $view_select_panel = new AphrontPanelView();
    $view_select_form = phutil_render_tag(
      'form',
      array(
        'action' => $request->getRequestURI(),
        'method' => 'get',
        'style'  => 'display: inline',
      ),
      $select.
      '<button>view</button>');
    $view_select_panel->appendChild($view_select_form);

    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $this->diffusionRequest);
    $file_content = $file_query->loadFileContent();

    // Build the content of the file.
    // TODO: image
    // TODO: blame.
    switch ($selected) {
      case 'plain':
        $style =
          "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
        $corpus = phutil_render_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          phutil_escape_html($file_content->getCorpus()));
          break;

      case 'highlighted':
      default:
        require_celerity_resource('syntax-highlighting-css');
        require_celerity_resource('diffusion-source-css');

        $path = $drequest->getPath();
        $highlightEngine = new PhutilDefaultSyntaxHighlighterEngine();
        $data = $highlightEngine->highlightSource($path,
          $file_content->getCorpus());
        $data = explode("\n", rtrim($data));

        $uri_path = $request->getPath();
        $uri_rev  = $drequest->getCommit();

        $color = null;
        $rows = array();
        $n = 1;
        foreach ($data as $k => $line) {
          if ($n == $drequest->getLine()) {
            $tr = '<tr style="background: #ffff00;">';
            $targ = '<a id="scroll_target"></a>';
            Javelin::initBehavior('diffusion-jump-to',
              array('target' => 'scroll_target'));
          } else {
            $tr = '<tr>';
            $targ = null;
          }

          $l = phutil_render_tag(
            'a',
            array(
              'href' => $uri_path.';'.$uri_rev.'$'.$n,
            ),
            $n);

          $rows[] = $tr.'<th>'.$l.'</th><td>'.$targ.$line.'</td></tr>';
          ++$n;
        }

        $corpus_table = phutil_render_tag(
          'table',
          array(
            'class' => "diffusion-source remarkup-code",
          ),
          implode("\n", $rows));
        $corpus = phutil_render_tag(
          'div',
          array(
            'style' => 'padding: 0pt 2em;',
          ),
          $corpus_table);

        break;
    }

    // Render the page.
    $content = array();
    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));
    $content[] = $view_select_panel;
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
