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

  // Image types we want to display inline using <img> tags
  protected $imageTypes = array(
    'png' => 'image/png',
    'gif' => 'image/gif',
    'ico' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg'=> 'image/jpeg'
  );

  // Document types that should trigger link to ?view=raw
  protected $documentTypes = array(
    'pdf'=> 'application/pdf',
    'ps' => 'application/postscript',
  );

  public function processRequest() {

    // Build the view selection form.
    $select_map = array(
      'highlighted' => 'View as Highlighted Text',
      'blame' => 'View as Highlighted Text with Blame',
      'plain' => 'View as Plain Text',
      'plainblame' => 'View as Plain Text with Blame',
      'raw' => 'View as raw document',
    );

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
      $response = new AphrontFileResponse();
      $response->setContent($data);
      $mime_type = $this->getDocumentType($path);
      if ($mime_type) {
        $response->setMimeType($mime_type);
      } else {
        $as_filename = idx(pathinfo($path), 'basename');
        $response->setDownload($as_filename);
      }
      return $response;
    }

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

    require_celerity_resource('diffusion-source-css');

    $view_select_panel = new AphrontPanelView();
    $view_select_form = phutil_render_tag(
      'form',
      array(
        'action' => $request->getRequestURI(),
        'method' => 'get',
        'class'  => 'diffusion-browse-type-form',
      ),
      $select.
      '<button>View</button>');
    $view_select_panel->appendChild($view_select_form);
    $view_select_panel->appendChild('<div style="clear: both;"></div>');

    // Build the content of the file.
    $corpus = $this->buildCorpus(
      $selected,
      $file_query,
      $needs_blame,
      $drequest,
      $path,
      $data
    );

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


  /**
   * Returns a content-type corrsponding to an image file extension
   *
   * @param string $path File path
   * @return mixed A content-type string or NULL if path doesn't end with a
   *               recognized image extension
   */
  public function getImageType($path) {
    $ext = pathinfo($path);
    $ext = idx($ext, 'extension');
    return idx($this->imageTypes, $ext);
  }

  /**
   * Returns a content-type corresponding to an document file extension
   *
   * @param string $path File path
   * @return mixed A content-type string or NULL if path doesn't end with a
   *               recognized document extension
   */
  public function getDocumentType($path) {
    $ext = pathinfo($path);
    $ext = idx($ext, 'extension');
    return idx($this->documentTypes, $ext);
  }


  private function buildCorpus($selected,
                               $file_query,
                               $needs_blame,
                               $drequest,
                               $path,
                               $data) {
    $image_type = $this->getImageType($path);
    if ($image_type && !$selected) {
      $corpus = phutil_render_tag(
        'img',
        array(
          'style' => 'padding-bottom: 10px',
          'src' => 'data:'.$image_type.';base64,'.base64_encode($data),
        )
      );
      return $corpus;
    }

    $document_type = $this->getDocumentType($path);
    if (($document_type && !$selected) || !phutil_is_utf8($data)) {
      $data = $file_query->getRawData();
      $document_type_description = $document_type ? $document_type : 'binary';
      $corpus = phutil_render_tag(
        'p',
        array(
          'style' => 'text-align: center;'
        ),
        phutil_render_tag(
          'a',
          array(
            'href' => '?view=raw',
            'class' => 'button'
          ),
          "View $document_type_description"
        )
      );
      return $corpus;
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


  private function buildDisplayRows($text_list, $rev_list, $blame_dict,
    $needs_blame, DiffusionRequest $drequest, $file_query, $selected) {
    $last_rev = null;
    $color = null;
    $rows = array();
    $n = 1;
    $view = $this->getRequest()->getStr('view');

    if ($blame_dict) {
      $epoch_list = ipull($blame_dict, 'epoch');
      $max = max($epoch_list);
      $min = min($epoch_list);
      $range = $max - $min + 1;
    } else {
      $range = 1;
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

          $color_number = (int)(0xEE -
            0xEE * ($blame_dict[$rev]['epoch'] - $min) / $range);
          $color = sprintf('#%02xee%02x', $color_number, $color_number);

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
              $selected);
            $prev_link = '<th style="background: ' . $color .
              '; width: 2em;">' . $prev_link . '</th>';
          }

          if (isset($blame_dict[$rev]['handle'])) {
            $author_link = $blame_dict[$rev]['handle']->renderLink();
          } else {
            $author_link = phutil_escape_html($blame_dict[$rev]['author']);
          }
          $blame_info =
            $prev_link .
            '<th style="background: '.$color.
              '; width: 12em;">'.$revision_link.'</th>'.
            '<th style="background: '.$color.'; width: 12em'.
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
      $uri_rev  = $drequest->getStableCommitName();
      $uri_view = $view
        ? '?view='.$view
        : null;

      $l = phutil_render_tag(
        'a',
        array(
          'class' => 'diffusion-line-link',
          'href' => $uri_path.';'.$uri_rev.'$'.$n.$uri_view,
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


  private static function renderBrowse(
    DiffusionRequest $drequest,
    $path,
    $name = null,
    $rev = null,
    $line = null,
    $view = null) {

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
      ),
      $name
    );
  }


}
