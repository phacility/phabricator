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

    $before = $request->getStr('before');
    if ($before) {
      return $this->buildBeforeResponse($before);
    }

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
                               DiffusionFileContentQuery $file_query,
                               $needs_blame,
                               DiffusionRequest $drequest,
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
            'style' => 'padding: 0 2em;',
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

  private function buildDisplayRows(
    array $text_list,
    array $rev_list,
    array $blame_dict,
    $needs_blame,
    DiffusionRequest $drequest,
    DiffusionFileContentQuery $file_query,
    $selected) {

    if ($blame_dict) {
      $epoch_list  = ipull(ifilter($blame_dict, 'epoch'), 'epoch');
      $epoch_min   = min($epoch_list);
      $epoch_max   = max($epoch_list);
      $epoch_range = ($epoch_max - $epoch_min) + 1;
    }

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

    $display = array();

    $line_number = 1;
    $last_rev = null;
    $color = null;
    foreach ($text_list as $k => $line) {
      $display_line = array(
        'color'       => null,
        'epoch'       => null,
        'commit'      => null,
        'author'      => null,
        'target'      => null,
        'highlighted' => null,
        'line'        => $line_number,
        'data'        => $line,
      );

      if ($needs_blame) {
        // If the line's rev is same as the line above, show empty content
        // with same color; otherwise generate blame info. The newer a change
        // is, the more saturated the color.

        // TODO: SVN doesn't always give us blame for the last line, if empty?
        // Bug with our stuff or with SVN?
        $rev = idx($rev_list, $k, $last_rev);

        if ($last_rev == $rev) {
          $display_line['color'] = $color;
        } else {
          $blame = $blame_dict[$rev];

          if (!isset($blame['epoch'])) {
            $color = '#ffd'; // Render as warning.
          } else {
            $color_ratio = ($blame['epoch'] - $epoch_min) / $epoch_range;
            $color_value = 0xF6 * (1.0 - $color_ratio);
            $color = sprintf(
              '#%02x%02x%02x',
              $color_value,
              0xF6,
              $color_value);
          }

          $display_line['epoch'] = idx($blame, 'epoch');
          $display_line['color'] = $color;
          $display_line['commit'] = $rev;

          if (isset($blame['handle'])) {
            $author_link = $blame['handle']->renderLink();
          } else {
            $author_link = phutil_render_tag(
              'span',
              array(
              ),
              phutil_escape_html($blame['author']));
          }
          $display_line['author'] = $author_link;

          $last_rev = $rev;
        }
      }

      if ($min_line) {
        if ($line_number == $min_line) {
          $display_line['target'] = true;
        }
        if ($line_number >= $min_line && $line_number <= $max_line) {
          $display_line['highlighted'] = true;
        }
      }

      $display[] = $display_line;
      ++$line_number;
    }

    $commits = id(new PhabricatorAuditCommitQuery())
      ->withIdentifiers(
        $drequest->getRepository()->getID(),
        array_filter(ipull($display, 'commit')))
      ->needCommitData(true)
      ->execute();
    $commits = mpull($commits, null, 'getCommitIdentifier');

    $request = $this->getRequest();
    $user = $request->getUser();

    $rows = array();
    foreach ($display as $line) {

      $line_href = $drequest->generateURI(
        array(
          'action'  => 'browse',
          'line'    => $line['line'],
          'stable'  => true,
        ));

      $line_href->setQueryParams($request->getRequestURI()->getQueryParams());

      $blame = array();
      if ($line['color']) {
        $color = $line['color'];

        $before_link = null;
        $commit_link = null;
        if (idx($line, 'commit')) {
          $commit = $line['commit'];

          $summary = 'Unknown';
          if (idx($commits, $commit)) {
            $summary = $commits[$commit]->getCommitData()->getSummary();
          }

          $tooltip = phabricator_date(
            $line['epoch'],
            $user)." \xC2\xB7 ".$summary;

          Javelin::initBehavior('phabricator-tooltips', array());
          require_celerity_resource('aphront-tooltip-css');

          $commit_link = javelin_render_tag(
            'a',
            array(
              'href' => $drequest->generateURI(
                array(
                  'action' => 'commit',
                  'commit' => $line['commit'],
                )),
              'sigil' => 'has-tooltip',
              'meta'  => array(
                'tip'   => $tooltip,
                'align' => 'E',
                'size'  => 600,
              ),
            ),
            phutil_escape_html(phutil_utf8_shorten($line['commit'], 9, '')));

          $before_link = javelin_render_tag(
            'a',
            array(
              'href'  => $line_href->alter('before', $commit),
              'sigil' => 'has-tooltip',
              'meta'  => array(
                'tip'     => 'Skip Past This Commit',
                'align'   => 'E',
                'size'    => 300,
              ),
            ),
            "\xC2\xAB");
        }

        $blame[] = phutil_render_tag(
          'th',
          array(
            'class' => 'diffusion-blame-link',
            'style' => 'background: '.$color,
          ),
          $before_link);

        $blame[] = phutil_render_tag(
          'th',
          array(
            'class' => 'diffusion-rev-link',
            'style' => 'background: '.$color,
          ),
          $commit_link);

        $blame[] = phutil_render_tag(
          'th',
          array(
            'class' => 'diffusion-author-link',
            'style' => 'background: '.$color,
          ),
          idx($line, 'author'));
      }

      $line_link = phutil_render_tag(
        'a',
        array(
          'href' => $line_href,
        ),
        phutil_escape_html($line['line']));

      $blame[] = phutil_render_tag(
        'th',
        array(
          'class' => 'diffusion-line-link',
          'style' => isset($color) ? 'background: '.$color : null,
        ),
        $line_link);

      $blame = implode('', $blame);

      if ($line['target']) {
        Javelin::initBehavior(
          'diffusion-jump-to',
          array(
            'target' => 'scroll_target',
          ));
        $anchor_text = '<a id="scroll_target"></a>';
      } else {
        $anchor_text = null;
      }

      $line_text = phutil_render_tag(
        'td',
        array(
        ),
        $anchor_text.$line['data']);

      $rows[] = phutil_render_tag(
        'tr',
        array(
          'style' => ($line['highlighted'] ? 'background: #ffff00;' : null),
        ),
        $blame.
        $line_text);
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

  private function buildBeforeResponse($before) {
    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();

    $before_req = DiffusionRequest::newFromDictionary(
      array(
        'repository' => $drequest->getRepository(),
        'commit'     => $before,
      ));

    $query = DiffusionCommitParentsQuery::newFromDiffusionRequest($before_req);
    $parents = $query->loadParents();
    $parent = head($parents);

    // NOTE: If they get back to the very first commit, we just keep them there.
    // We could maybe show a message or something.

    $before_uri = $drequest->generateURI(
      array(
        'action'    => 'browse',
        'commit'    => $parent ? $parent->getCommitIdentifier() : $before,
        'line'      => $drequest->getLine(),
      ));

    $before_uri->setQueryParams($request->getRequestURI()->getQueryParams());
    $before_uri = $before_uri->alter('before', null);

    return id(new AphrontRedirectResponse())->setURI($before_uri);
  }

}
