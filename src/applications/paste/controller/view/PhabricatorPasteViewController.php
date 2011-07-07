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

class PhabricatorPasteViewController extends PhabricatorPasteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $paste = id(new PhabricatorPaste())->load($this->id);
    if (!$paste) {
      return new Aphront404Response();
    }

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $paste->getFilePHID());
    if (!$file) {
      return new Aphront400Response();
    }

    $corpus = $this->buildCorpus($paste, $file);

    $panel = new AphrontPanelView();

    if (strlen($paste->getTitle())) {
      $panel->setHeader(
        'Viewing Paste '.$paste->getID().' - '.
        phutil_escape_html($paste->getTitle()));
    } else {
      $panel->setHeader('Viewing Paste '.$paste->getID());
    }

    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/paste/?copy='.$paste->getID(),
          'class' => 'green button',
        ),
        'Copy This'));

    $raw_uri = PhabricatorFileURI::getViewURIForPHID($paste->getFilePHID());
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href'  => $raw_uri,
          'class' => 'button',
        ),
        'View Raw Text'));

    $panel->appendChild($corpus);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Paste: '.nonempty($paste->getTitle(), 'P'.$paste->getID()),
        'tab' => 'view',
      ));
  }

  private function buildCorpus($paste, $file) {
    // Blantently copied from DiffusionBrowseFileController

    require_celerity_resource('diffusion-source-css');
    require_celerity_resource('syntax-highlighting-css');

    $language = $paste->getLanguage();
    $source = $file->loadFileData();
    if (empty($language)) {
      $source = PhabricatorSyntaxHighlighter::highlightWithFilename(
        $paste->getTitle(),
        $source);
    } else {
      $source = PhabricatorSyntaxHighlighter::highlightWithLanguage(
        $language,
        $source);
    }

    $text_list = explode("\n", $source);

    $rows = $this->buildDisplayRows($text_list);

    $corpus_table = phutil_render_tag(
      'table',
      array(
        'class' => 'diffusion-source remarkup-code PhabricatorMonospaced',
      ),
      implode("\n", $rows));

    $corpus = phutil_render_tag(
      'div',
      array(
        'style' => 'padding: 0pt 2em;',
      ),
      $corpus_table);

    return $corpus;
  }

  private function buildDisplayRows($text_list) {
    $rows = array();
    $n = 1;

    foreach ($text_list as $k => $line) {
      // Pardon the ugly for the time being.
      // And eventually this will highlight a line that you click
      // like diffusion does. Or maybe allow for line comments
      // like differential. Either way it will be better than it is now.
      $rows[] = '<tr><th>'.$n.'</th>'.
        '<td style="white-space: pre-wrap;">'.$line.'</td></tr>';
      ++$n;
    }

    return $rows;
  }

}
