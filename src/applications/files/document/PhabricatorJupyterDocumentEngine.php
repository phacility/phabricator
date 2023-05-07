<?php

final class PhabricatorJupyterDocumentEngine
  extends PhabricatorDocumentEngine {

  const ENGINEKEY = 'jupyter';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as Jupyter Notebook');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-sun-o';
  }

  protected function getDocumentRenderingText(PhabricatorDocumentRef $ref) {
    return pht('Rendering Jupyter Notebook...');
  }

  public function shouldRenderAsync(PhabricatorDocumentRef $ref) {
    return true;
  }

  protected function getContentScore(PhabricatorDocumentRef $ref) {
    $name = $ref->getName();

    if (preg_match('/\\.ipynb\z/i', $name)) {
      return 2000;
    }

    return 500;
  }

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    return $ref->isProbablyJSON();
  }

  public function canDiffDocuments(
    PhabricatorDocumentRef $uref = null,
    PhabricatorDocumentRef $vref = null) {
    return true;
  }

  public function newEngineBlocks(
    PhabricatorDocumentRef $uref = null,
    PhabricatorDocumentRef $vref = null) {

    $blocks = new PhabricatorDocumentEngineBlocks();

    try {
      if ($uref) {
        $u_blocks = $this->newDiffBlocks($uref);
      } else {
        $u_blocks = array();
      }

      if ($vref) {
        $v_blocks = $this->newDiffBlocks($vref);
      } else {
        $v_blocks = array();
      }

      $blocks->addBlockList($uref, $u_blocks);
      $blocks->addBlockList($vref, $v_blocks);
    } catch (Exception $ex) {
      phlog($ex);
      $blocks->addMessage($ex->getMessage());
    }

    return $blocks;
  }

  public function newBlockDiffViews(
    PhabricatorDocumentRef $uref,
    PhabricatorDocumentEngineBlock $ublock,
    PhabricatorDocumentRef $vref,
    PhabricatorDocumentEngineBlock $vblock) {

    $ucell = $ublock->getContent();
    $vcell = $vblock->getContent();

    $utype = idx($ucell, 'cell_type');
    $vtype = idx($vcell, 'cell_type');

    if ($utype === $vtype) {
      switch ($utype) {
        case 'markdown':
          $usource = $this->readString($ucell, 'source');
          $vsource = $this->readString($vcell, 'source');

          $diff = id(new PhutilProseDifferenceEngine())
            ->getDiff($usource, $vsource);

          $u_content = $this->newProseDiffCell($diff, array('=', '-'));
          $v_content = $this->newProseDiffCell($diff, array('=', '+'));

          $u_content = $this->newJupyterCell(null, $u_content, null);
          $v_content = $this->newJupyterCell(null, $v_content, null);

          $u_content = $this->newCellContainer($u_content);
          $v_content = $this->newCellContainer($v_content);

          return id(new PhabricatorDocumentEngineBlockDiff())
            ->setOldContent($u_content)
            ->addOldClass('old')
            ->setNewContent($v_content)
            ->addNewClass('new');
        case 'code/line':
          $usource = idx($ucell, 'raw');
          $vsource = idx($vcell, 'raw');
          $udisplay = idx($ucell, 'display');
          $vdisplay = idx($vcell, 'display');

          $intraline_segments = ArcanistDiffUtils::generateIntralineDiff(
            $usource,
            $vsource);

          $u_segments = array();
          foreach ($intraline_segments[0] as $u_segment) {
            $u_segments[] = $u_segment;
          }

          $v_segments = array();
          foreach ($intraline_segments[1] as $v_segment) {
            $v_segments[] = $v_segment;
          }

          $usource = PhabricatorDifferenceEngine::applyIntralineDiff(
            $udisplay,
            $u_segments);

          $vsource = PhabricatorDifferenceEngine::applyIntralineDiff(
            $vdisplay,
            $v_segments);

          list($u_label, $u_content) = $this->newCodeLineCell($ucell, $usource);
          list($v_label, $v_content) = $this->newCodeLineCell($vcell, $vsource);

          $classes = array(
            'jupyter-cell-flush',
          );

          $u_content = $this->newJupyterCell($u_label, $u_content, $classes);
          $v_content = $this->newJupyterCell($v_label, $v_content, $classes);

          $u_content = $this->newCellContainer($u_content);
          $v_content = $this->newCellContainer($v_content);

          return id(new PhabricatorDocumentEngineBlockDiff())
            ->setOldContent($u_content)
            ->addOldClass('old')
            ->setNewContent($v_content)
            ->addNewClass('new');
      }
    }

    return parent::newBlockDiffViews($uref, $ublock, $vref, $vblock);
  }

  public function newBlockContentView(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngineBlock $block) {

    $viewer = $this->getViewer();
    $cell = $block->getContent();

    $cell_content = $this->renderJupyterCell($viewer, $cell);

    return $this->newCellContainer($cell_content);
  }

  private function newCellContainer($cell_content) {
    $notebook_table = phutil_tag(
      'table',
      array(
        'class' => 'jupyter-notebook',
      ),
      $cell_content);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-jupyter document-engine-diff',
      ),
      $notebook_table);

    return $container;
  }

  private function newProseDiffCell(PhutilProseDiff $diff, array $mask) {
    $mask = array_fuse($mask);

    $result = array();
    foreach ($diff->getParts() as $part) {
      $type = $part['type'];
      $text = $part['text'];

      if (!isset($mask[$type])) {
        continue;
      }

      switch ($type) {
        case '-':
          $result[] = phutil_tag(
            'span',
            array(
              'class' => 'bright',
            ),
            $text);
          break;
        case '+':
          $result[] = phutil_tag(
            'span',
            array(
              'class' => 'bright',
            ),
            $text);
          break;
        case '=':
          $result[] = $text;
          break;
      }
    }

    return array(
      null,
      phutil_tag(
        'div',
        array(
          'class' => 'jupyter-cell-markdown',
        ),
        $result),
    );
  }

  private function newDiffBlocks(PhabricatorDocumentRef $ref) {
    $viewer = $this->getViewer();
    $content = $ref->loadData();

    $cells = $this->newCells($content, true);

    $idx = 1;
    $blocks = array();
    foreach ($cells as $cell) {
      // When the cell is a source code line, we can hash just the raw
      // input rather than all the cell metadata.

      switch (idx($cell, 'cell_type')) {
        case 'code/line':
          $hash_input = $cell['raw'];
          break;
        case 'markdown':
          $hash_input = $this->readString($cell, 'source');
          break;
        default:
          $hash_input = serialize($cell);
          break;
      }

      $hash = PhabricatorHash::digestWithNamedKey(
        $hash_input,
        'document-engine.content-digest');

      $blocks[] = id(new PhabricatorDocumentEngineBlock())
        ->setBlockKey($idx)
        ->setDifferenceHash($hash)
        ->setContent($cell);

      $idx++;
    }

    return $blocks;
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $viewer = $this->getViewer();
    $content = $ref->loadData();

    try {
      $cells = $this->newCells($content, false);
    } catch (Exception $ex) {
      return $this->newMessage($ex->getMessage());
    }

    $rows = array();
    foreach ($cells as $cell) {
      $rows[] = $this->renderJupyterCell($viewer, $cell);
    }

    $notebook_table = phutil_tag(
      'table',
      array(
        'class' => 'jupyter-notebook',
      ),
      $rows);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-jupyter',
      ),
      $notebook_table);

    return $container;
  }

  private function newCells($content, $for_diff) {
    try {
      $data = phutil_json_decode($content);
    } catch (PhutilJSONParserException $ex) {
      throw new Exception(
        pht(
          'This is not a valid JSON document and can not be rendered as '.
          'a Jupyter notebook: %s.',
          $ex->getMessage()));
    }

    if (!is_array($data)) {
      throw new Exception(
        pht(
          'This document does not encode a valid JSON object and can not '.
          'be rendered as a Jupyter notebook.'));
    }

    $nbformat = idx($data, 'nbformat');
    if ($nbformat == null || !strlen($nbformat)) {
      throw new Exception(
        pht(
          'This document is missing an "nbformat" field. Jupyter notebooks '.
          'must have this field.'));
    }

    if ($nbformat !== 4) {
      throw new Exception(
        pht(
          'This Jupyter notebook uses an unsupported version of the file '.
          'format (found version %s, expected version 4).',
          $nbformat));
    }

    $cells = idx($data, 'cells');
    if (!is_array($cells)) {
      throw new Exception(
        pht(
          'This Jupyter notebook does not specify a list of "cells".'));
    }

    if (!$cells) {
      throw new Exception(
        pht(
          'This Jupyter notebook does not specify any notebook cells.'));
    }

    if (!$for_diff) {
      return $cells;
    }

    // If we're extracting cells to build a diff view, split code cells into
    // individual lines and individual outputs. We want users to be able to
    // add inline comments to each line and each output block.

    $results = array();
    foreach ($cells as $cell) {
      $cell_type = idx($cell, 'cell_type');
      if ($cell_type === 'markdown') {
        $source = $this->readString($cell, 'source');

        // Attempt to split contiguous blocks of markdown into smaller
        // pieces.

        $chunks = preg_split(
          '/\n\n+/',
          $source);

        foreach ($chunks as $chunk) {
          $result = $cell;
          $result['source'] = array($chunk);
          $results[] = $result;
        }

        continue;
      }

      if ($cell_type !== 'code') {
        $results[] = $cell;
        continue;
      }

      $label = $this->newCellLabel($cell);

      $lines = $this->readStringList($cell, 'source');
      $content = $this->highlightLines($lines);

      $count = count($lines);
      for ($ii = 0; $ii < $count; $ii++) {
        $is_head = ($ii === 0);
        $is_last = ($ii === ($count - 1));

        if ($is_head) {
          $line_label = $label;
        } else {
          $line_label = null;
        }

        $results[] = array(
          'cell_type' => 'code/line',
          'label' => $line_label,
          'raw' => $lines[$ii],
          'display' => idx($content, $ii),
          'head' => $is_head,
          'last' => $is_last,
        );
      }

      $outputs = array();
      $output_list = idx($cell, 'outputs');
      if (is_array($output_list)) {
        foreach ($output_list as $output) {
          $results[] = array(
            'cell_type' => 'code/output',
            'output' => $output,
          );
        }
      }
    }

    return $results;
  }


  private function renderJupyterCell(
    PhabricatorUser $viewer,
    array $cell) {

    list($label, $content) = $this->renderJupyterCellContent($viewer, $cell);

    $classes = null;
    switch (idx($cell, 'cell_type')) {
      case 'code/line':
        $classes = 'jupyter-cell-flush';
        break;
    }

    return $this->newJupyterCell(
      $label,
      $content,
      $classes);
  }

  private function newJupyterCell($label, $content, $classes) {
    $label_cell = phutil_tag(
      'td',
      array(
        'class' => 'jupyter-label',
      ),
      $label);

    $content_cell = phutil_tag(
      'td',
      array(
        'class' => $classes,
      ),
      $content);

    return phutil_tag(
      'tr',
      array(),
      array(
        $label_cell,
        $content_cell,
      ));
  }

  private function renderJupyterCellContent(
    PhabricatorUser $viewer,
    array $cell) {

    $cell_type = idx($cell, 'cell_type');
    switch ($cell_type) {
      case 'markdown':
        return $this->newMarkdownCell($cell);
      case 'code':
        return $this->newCodeCell($cell);
      case 'code/line':
        return $this->newCodeLineCell($cell);
      case 'code/output':
        return $this->newCodeOutputCell($cell);
    }

    $json_content = id(new PhutilJSON())
      ->encodeFormatted($cell);

    return $this->newRawCell($json_content);
  }

  private function newRawCell($content) {
    return array(
      null,
      phutil_tag(
        'div',
        array(
          'class' => 'jupyter-cell-raw PhabricatorMonospaced',
        ),
        $content),
    );
  }

  private function newMarkdownCell(array $cell) {
    $content = $this->readStringList($cell, 'source');

    // TODO: This should ideally highlight as Markdown, but the "md"
    // highlighter in Pygments is painfully slow and not terribly useful.
    $content = $this->highlightLines($content, 'txt');

    return array(
      null,
      phutil_tag(
        'div',
        array(
          'class' => 'jupyter-cell-markdown',
        ),
        $content),
    );
  }

  private function newCodeCell(array $cell) {
    $label = $this->newCellLabel($cell);

    $content = $this->readStringList($cell, 'source');
    $content = $this->highlightLines($content);

    $outputs = array();
    $output_list = idx($cell, 'outputs');
    if (is_array($output_list)) {
      foreach ($output_list as $output) {
        $outputs[] = $this->newOutput($output);
      }
    }

    return array(
      $label,
      array(
        phutil_tag(
          'div',
          array(
            'class' =>
              'jupyter-cell-code jupyter-cell-code-block '.
              'PhabricatorMonospaced remarkup-code',
          ),
          array(
            $content,
          )),
        $outputs,
      ),
    );
  }

  private function newCodeLineCell(array $cell, $content = null) {
    $classes = array();
    $classes[] = 'PhabricatorMonospaced';
    $classes[] = 'remarkup-code';
    $classes[] = 'jupyter-cell-code';
    $classes[] = 'jupyter-cell-code-line';

    if ($cell['head']) {
      $classes[] = 'jupyter-cell-code-head';
    }

    if ($cell['last']) {
      $classes[] = 'jupyter-cell-code-last';
    }

    $classes = implode(' ', $classes);

    if ($content === null) {
      $content = $cell['display'];
    }

    return array(
      $cell['label'],
      array(
        phutil_tag(
          'div',
          array(
            'class' => $classes,
          ),
          array(
            $content,
          )),
      ),
    );
  }

  private function newCodeOutputCell(array $cell) {
    return array(
      null,
      $this->newOutput($cell['output']),
    );
  }

  private function newOutput(array $output) {
    if (!is_array($output)) {
      return pht('<Invalid Output>');
    }

    $classes = array(
      'jupyter-output',
      'PhabricatorMonospaced',
    );

    $output_name = idx($output, 'name');
    switch ($output_name) {
      case 'stderr':
        $classes[] = 'jupyter-output-stderr';
        break;
    }

    $output_type = idx($output, 'output_type');
    switch ($output_type) {
      case 'execute_result':
      case 'display_data':
        $data = idx($output, 'data');

        $image_formats = array(
          'image/png',
          'image/jpeg',
          'image/jpg',
          'image/gif',
        );

        foreach ($image_formats as $image_format) {
          if (!isset($data[$image_format])) {
            continue;
          }

          $raw_data = $this->readString($data, $image_format);

          $content = phutil_tag(
            'img',
            array(
              'src' => 'data:'.$image_format.';base64,'.$raw_data,
            ));

          break 2;
        }

        if (isset($data['text/html'])) {
          $content = $data['text/html'];
          $classes[] = 'jupyter-output-html';
          break;
        }

        if (isset($data['application/javascript'])) {
          $content = $data['application/javascript'];
          $classes[] = 'jupyter-output-html';
          break;
        }

        if (isset($data['text/plain'])) {
          $content = $data['text/plain'];
          break;
        }

        break;
      case 'stream':
      default:
        $content = $this->readString($output, 'text');
        break;
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $content);
  }

  private function newCellLabel(array $cell) {
    $execution_count = idx($cell, 'execution_count');
    if ($execution_count) {
      $label = 'In ['.$execution_count.']:';
    } else {
      $label = null;
    }

    return $label;
  }

  private function highlightLines(array $lines, $force_language = null) {
    if ($force_language === null) {
      $head = head($lines);
      $matches = null;
      if (preg_match('/^%%(.*)$/', $head, $matches)) {
        $restore = array_shift($lines);
        $lang = $matches[1];
      } else {
        $restore = null;
        $lang = 'py';
      }
    } else {
      $restore = null;
      $lang = $force_language;
    }

    $content = PhabricatorSyntaxHighlighter::highlightWithLanguage(
      $lang,
      implode('', $lines));
    $content = phutil_split_lines($content);

    if ($restore !== null) {
      $language_tag = phutil_tag(
        'span',
        array(
          'class' => 'language-tag',
        ),
        $restore);

      array_unshift($content, $language_tag);
    }

    return $content;
  }

  public function shouldSuggestEngine(PhabricatorDocumentRef $ref) {
    return true;
  }

  private function readString(array $src, $key) {
    $list = $this->readStringList($src, $key);
    return implode('', $list);
  }

  private function readStringList(array $src, $key) {
    $list = idx($src, $key);

    if (is_array($list)) {
      $list = $list;
    } else if (is_string($list)) {
      $list = array($list);
    } else {
      $list = array();
    }

    return $list;
  }

}
