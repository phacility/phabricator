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
      'blame' => 'View as Highlighted Text with Blame',
      'plain' => 'View as Plain Text',
      'plainblame' => 'View as Plain Text with Blame',
    );

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

    // Build the content of the file.
    $corpus = $this->buildCorpus($selected);

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


  private function buildCorpus($selected) {
    $needs_blame = ($selected == 'blame' || $selected == 'plainblame');

    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $this->diffusionRequest);
    $file_query->setNeedsBlame($needs_blame);
    $file_query->loadFileContent();

    // TODO: image
    // TODO: blame of blame.
    switch ($selected) {
      case 'plain':
        $style =
          "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
        $corpus = phutil_render_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          phutil_escape_html($file_query->getRawData()));

          break;

      case 'plainblame':
        $style =
          "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
        list($text_list, $rev_list, $blame_dict) =
          $file_query->getBlameData();

        $rows = array();
        foreach ($text_list as $k => $line) {
          $rev = $rev_list[$k];
          $author = $blame_dict[$rev]['author'];
          $rows[] =
            sprintf("%-10s %-15s %s", substr($rev, 0, 7), $author, $line);
        }

        $corpus = phutil_render_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          phutil_escape_html(implode("\n", $rows)));

        break;

      case 'highlighted':
      case 'blame':
      default:
        require_celerity_resource('syntax-highlighting-css');
        require_celerity_resource('diffusion-source-css');

        list($text_list, $rev_list, $blame_dict) = $file_query->getBlameData();

        $drequest = $this->getDiffusionRequest();
        $path = $drequest->getPath();
        $highlightEngine = new PhutilDefaultSyntaxHighlighterEngine();

        $text_list = explode("\n", $highlightEngine->highlightSource($path,
          implode("\n", $text_list)));

        $rows = $this->buildDisplayRows($text_list, $rev_list, $blame_dict,
          $needs_blame, $drequest);

        $corpus_table = phutil_render_tag(
          'table',
          array(
            'class' => "diffusion-source remarkup-code PhabricatorMonospaced",
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

    return $corpus;
  }


  private static function buildDisplayRows($text_list, $rev_list, $blame_dict,
    $needs_blame, DiffusionRequest $drequest) {
    $last_rev = null;
    $color = null;
    $rows = array();
    $n = 1;

    $epoch_list = ipull($blame_dict, 'epoch');
    $max = max($epoch_list);
    $min = min($epoch_list);
    $range = $max - $min + 1;

    foreach ($text_list as $k => $line) {
      if ($needs_blame) {
        // If the line's rev is same as the line above, show empty content
        // with same color; otherwise generate blame info. The newer a change
        // is, the darker the color.
        $rev = $rev_list[$k];
        if ($last_rev == $rev) {
          $blame_info =
            '<th style="background: '.$color.'; width: 9em;"></th>'.
            '<th style="background: '.$color.'"></th>';
        } else {

          $color_number = (int)(0xEE -
            0xEE * ($blame_dict[$rev]['epoch'] - $min) / $range);
          $color = sprintf('#%02xee%02x', $color_number, $color_number);

          $revision_link = self::renderRevision(
            $drequest,
            substr($rev, 0, 7));

          $author_link = $blame_dict[$rev]['author'];
          $blame_info =
            '<th style="background: '.$color.
              '; width: 9em;">'.$revision_link.'</th>'.
            '<th style="background: '.$color.
              '; font-weight: normal; color: #333;">'.$author_link.'</th>';
          $last_rev = $rev;
        }
      } else {
        $blame_info = null;
      }

      // Highlight the line of interest if needed.
      if ($n == $drequest->getLine()) {
        $tr = '<tr style="background: #ffff00;">';
        $targ = '<a id="scroll_target"></a>';
        Javelin::initBehavior('diffusion-jump-to',
          array('target' => 'scroll_target'));
      } else {
        $tr = '<tr>';
        $targ = null;
      }

      // Create the row display.
      $uri_path = $drequest->getUriPath();
      $uri_rev  = $drequest->getCommit();

      $l = phutil_render_tag(
        'a',
        array(
          'href' => $uri_path.';'.$uri_rev.'$'.$n,
        ),
        $n);

      $rows[] = $tr.$blame_info.'<th>'.$l.'</th><td>'.$targ.$line.'</td></tr>';
      ++$n;
    }

    return $rows;
  }


  private static function renderRevision(DiffusionRequest $drequest,
    $revision) {

    $callsign = $drequest->getCallsign();

    $name = 'r'.$callsign.$revision;
    return phutil_render_tag(
      'a',
      array(
           'href' => '/'.$name,
      ),
      $name
    );
  }

}
