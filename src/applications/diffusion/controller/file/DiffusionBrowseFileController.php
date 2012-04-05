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

final class DiffusionBrowseFileController extends DiffusionController {

  private $corpusType = 'text';

  public function processRequest() {

    $request = $this->getRequest();

    $drequest = $this->getDiffusionRequest();
    $path = $drequest->getPath();
    $selected = $request->getStr('view');
    $needs_blame = ($selected == 'blame' || $selected == 'plainblame');
    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $this->diffusionRequest);
    $file_query->setNeedsBlame($needs_blame);
    $file_query->loadFileContent();
    $data = $file_query->getRawData();

    if ($selected === 'raw') {
      return $this->buildRawResponse($path, $data);
    }

    // Build the content of the file.
    $corpus = $this->buildCorpus(
      $selected,
      $file_query,
      $needs_blame,
      $drequest,
      $path,
      $data);

    require_celerity_resource('diffusion-source-css');

    if ($this->corpusType == 'text') {
      $view_select_panel = $this->renderViewSelectPanel();
    } else {
      $view_select_panel = null;
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
    $content[] = $this->buildOpenRevisions();

    $nav = $this->buildSideNav('browse', true);
    $nav->appendChild($content);

    $basename = basename($this->getDiffusionRequest()->getPath());

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => $basename,
      ));
  }

  private function buildCorpus($selected,
                               $file_query,
                               $needs_blame,
                               $drequest,
                               $path,
                               $data) {

    if (ArcanistDiffUtils::isHeuristicBinaryFile($data)) {
      $file = $this->loadFileForData($path, $data);
      $file_uri = $file->getBestURI();

      if ($file->isViewableImage()) {
        $this->corpusType = 'image';
        return $this->buildImageCorpus($file_uri);
      } else {
        $this->corpusType = 'binary';
        return $this->buildBinaryCorpus($file_uri, $data);
      }
    }


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
          if (isset($blame_dict[$rev]['handle'])) {
            $author = $blame_dict[$rev]['handle']->getName();
          } else {
            $author = $blame_dict[$rev]['author'];
          }
          $rows[] =
            sprintf("%-10s %-20s %s", substr($rev, 0, 7), $author, $line);
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

        list($text_list, $rev_list, $blame_dict) = $file_query->getBlameData();

        $text_list = implode("\n", $text_list);
        $text_list = PhabricatorSyntaxHighlighter::highlightWithFilename(
          $path,
          $text_list);
        $text_list = explode("\n", $text_list);

        $rows = $this->buildDisplayRows($text_list, $rev_list, $blame_dict,
          $needs_blame, $drequest, $file_query, $selected);

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

  private function renderViewSelectPanel() {

    $request = $this->getRequest();

    $select = AphrontFormSelectControl::renderSelectTag(
      $request->getStr('view'),
      array(
        'highlighted'   => 'View as Highlighted Text',
        'blame'         => 'View as Highlighted Text with Blame',
        'plain'         => 'View as Plain Text',
        'plainblame'    => 'View as Plain Text with Blame',
        'raw'           => 'View as raw document',
      ),
      array(
        'name' => 'view',
      ));

    $view_select_panel = new AphrontPanelView();
    $view_select_form = phutil_render_tag(
      'form',
      array(
        'action' => $request->getRequestURI(),
        'method' => 'get',
        'class'  => 'diffusion-browse-type-form',
      ),
      $select.
      ' <button>View</button> '.
      $this->renderEditButton());

    $view_select_panel->appendChild($view_select_form);
    $view_select_panel->appendChild('<div style="clear: both;"></div>');

    return $view_select_panel;
  }

  private function renderEditButton() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $drequest = $this->getDiffusionRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $line = 1;

    $callsign = $repository->getCallsign();
    $editor_link = $user->loadEditorLink($path, $line, $callsign);
    if (!$editor_link) {
      return null;
    }

    return phutil_render_tag(
      'a',
      array(
        'href' => $editor_link,
        'class' => 'button',
      ),
      'Edit');
  }

  private function buildDisplayRows($text_list, $rev_list, $blame_dict,
    $needs_blame, DiffusionRequest $drequest, $file_query, $selected) {
    $last_rev = null;
    $color = '#eeeeee';
    $rows = array();
    $n = 1;
    $view = $this->getRequest()->getStr('view');

    if ($blame_dict) {
      $epoch_list = ipull($blame_dict, 'epoch');
      $epoch_max = max($epoch_list);
      $epoch_min = min($epoch_list);
      $epoch_range = $epoch_max - $epoch_min + 1;
    }

    $targ = '';
    $min_line = 0;
    $line = $drequest->getLine();
    if (strpos($line, '-') !== false) {
      list($min, $max) = explode('-', $line, 2);
      $min_line = min($min, $max);
      $max_line = max($min, $max);
    } else if (strlen($line)) {
      $min_line = $line;
      $max_line = $line;
    }

    foreach ($text_list as $k => $line) {
      if ($needs_blame) {
        // If the line's rev is same as the line above, show empty content
        // with same color; otherwise generate blame info. The newer a change
        // is, the darker the color.
        $rev = $rev_list[$k];
        if ($last_rev == $rev) {
          $blame_info =
            ($file_query->getSupportsBlameOnBlame() ?
              '<th style="background: '.$color.'; width: 2em;"></th>' : '').
            '<th style="background: '.$color.'; width: 9em;"></th>'.
            '<th style="background: '.$color.'"></th>';
        } else {

          $revision_time = null;
          if ($blame_dict) {
            $color_number = (int)(0xEE -
              0xEE * ($blame_dict[$rev]['epoch'] - $epoch_min) / $epoch_range);
            $color = sprintf('#%02xee%02x', $color_number, $color_number);
            $revision_time = phabricator_datetime(
              $blame_dict[$rev]['epoch'],
              $this->getRequest()->getUser());
          }

          $revision_link = self::renderRevision(
            $drequest,
            substr($rev, 0, 7));

          if (!$file_query->getSupportsBlameOnBlame()) {
            $prev_link = '';
          } else {
            $prev_rev = $file_query->getPrevRev($rev);
            $path = $drequest->getPath();
            $prev_link = self::renderBrowse(
              $drequest,
              $path,
              "\xC2\xAB",
              $prev_rev,
              $n,
              $selected,
              'Blame previous revision');
            $prev_link = phutil_render_tag(
              'th',
              array(
                'class' => 'diffusion-wide-link',
                'style' => 'background: '.$color.'; width: 2em;',
              ),
              $prev_link);
          }

          if (isset($blame_dict[$rev]['handle'])) {
            $author_link = $blame_dict[$rev]['handle']->renderLink();
          } else {
            $author_link = phutil_escape_html($blame_dict[$rev]['author']);
          }
          $blame_info =
            $prev_link .
            '<th style="background: '.$color.'; width: 12em;" title="'.
            phutil_escape_html($revision_time).'">'.$revision_link.'</th>'.
            '<th style="background: '.$color.'; width: 12em'.
              '; font-weight: normal; color: #333;">'.$author_link.'</th>';
          $last_rev = $rev;
        }
      } else {
        $blame_info = null;
      }

      // Highlight the line of interest if needed.
      if ($min_line > 0 && ($n >= $min_line && $n <= $max_line)) {
        $tr = '<tr style="background: #ffff00;">';
        if ($targ == '') {
          $targ = '<a id="scroll_target"></a>';
          Javelin::initBehavior('diffusion-jump-to',
            array('target' => 'scroll_target'));
        }
      } else {
        $tr = '<tr>';
        $targ = null;
      }

      $href = $drequest->generateURI(
        array(
          'action' => 'browse',
          'stable' => true,
        ));
      $href = (string)$href;

      $query_params = null;
      if ($view) {
        $query_params = '?view='.$view;
      }

      $link = phutil_render_tag(
        'a',
        array(
          'href' => $href.'$'.$n.$query_params,
        ),
        $n);

      $rows[] = $tr.$blame_info.
        '<th class="diffusion-wide-link">'.$link.'</th>'.
        '<td>'.$targ.$line.'</td></tr>';
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


  private static function renderBrowse(
    DiffusionRequest $drequest,
    $path,
    $name = null,
    $rev = null,
    $line = null,
    $view = null,
    $title = null) {

    $callsign = $drequest->getCallsign();

    if ($name === null) {
      $name = $path;
    }

    $at = null;
    if ($rev) {
      $at = ';'.$rev;
    }

    if ($view) {
      $view = '?view='.$view;
    }

    if ($line) {
      $line = '$'.$line;
    }

    return phutil_render_tag(
      'a',
      array(
        'href' => "/diffusion/{$callsign}/browse/{$path}{$at}{$line}{$view}",
        'title' => $title,
      ),
      $name
    );
  }

  private function loadFileForData($path, $data) {
    $hash = PhabricatorHash::digest($data);

    $file = id(new PhabricatorFile())->loadOneWhere(
      'contentHash = %s LIMIT 1',
      $hash);
    if (!$file) {
      // We're just caching the data; this is always safe.
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $file = PhabricatorFile::newFromFileData(
        $data,
        array(
          'name' => basename($path),
        ));

      unset($unguarded);
    }

    return $file;
  }

  private function buildRawResponse($path, $data) {
    $file = $this->loadFileForData($path, $data);
    return id(new AphrontRedirectResponse())->setURI($file->getBestURI());
  }

  private function buildImageCorpus($file_uri) {
    $panel = new AphrontPanelView();
    $panel->setHeader('Image');
    $panel->addButton($this->renderEditButton());
    $panel->appendChild(
      phutil_render_tag(
        'img',
        array(
          'src' => $file_uri,
        )));
    return $panel;
  }

  private function buildBinaryCorpus($file_uri, $data) {
    $panel = new AphrontPanelView();
    $panel->setHeader('Binary File');
    $panel->addButton($this->renderEditButton());
    $panel->appendChild(
      '<p>'.
        'This is a binary file. '.
        'It is '.number_format(strlen($data)).' bytes in length.'.
      '</p>');
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => $file_uri,
          'class' => 'button green',
        ),
        'Download Binary File...'));
    return $panel;
  }


}
