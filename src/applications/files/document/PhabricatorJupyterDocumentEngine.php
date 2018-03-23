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

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $viewer = $this->getViewer();
    $content = $ref->loadData();

    try {
      $data = phutil_json_decode($content);
    } catch (PhutilJSONParserException $ex) {
      return $this->newMessage(
        pht(
          'This is not a valid JSON document and can not be rendered as '.
          'a Jupyter notebook: %s.',
          $ex->getMessage()));
    }

    if (!is_array($data)) {
      return $this->newMessage(
        pht(
          'This document does not encode a valid JSON object and can not '.
          'be rendered as a Jupyter notebook.'));
    }


    $nbformat = idx($data, 'nbformat');
    if (!strlen($nbformat)) {
      return $this->newMessage(
        pht(
          'This document is missing an "nbformat" field. Jupyter notebooks '.
          'must have this field.'));
    }

    if ($nbformat !== 4) {
      return $this->newMessage(
        pht(
          'This Jupyter notebook uses an unsupported version of the file '.
          'format (found version %s, expected version 4).',
          $nbformat));
    }

    $cells = idx($data, 'cells');
    if (!is_array($cells)) {
      return $this->newMessage(
        pht(
          'This Jupyter notebook does not specify a list of "cells".'));
    }

    if (!$cells) {
      return $this->newMessage(
        pht(
          'This Jupyter notebook does not specify any notebook cells.'));
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

  private function renderJupyterCell(
    PhabricatorUser $viewer,
    array $cell) {

    list($label, $content) = $this->renderJupyterCellContent($viewer, $cell);

    $label_cell = phutil_tag(
      'th',
      array(),
      $label);

    $content_cell = phutil_tag(
      'td',
      array(),
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
    }

    return $this->newRawCell(id(new PhutilJSON())->encodeFormatted($cell));
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
    $content = idx($cell, 'source');
    if (!is_array($content)) {
      $content = array();
    }

    $content = implode('', $content);
    $content = phutil_escape_html_newlines($content);

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
    $execution_count = idx($cell, 'execution_count');
    if ($execution_count) {
      $label = 'In ['.$execution_count.']:';
    } else {
      $label = null;
    }

    $content = idx($cell, 'source');
    if (!is_array($content)) {
      $content = array();
    }

    $content = implode('', $content);

    $content = PhabricatorSyntaxHighlighter::highlightWithLanguage(
      'python',
      $content);

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
            'class' => 'jupyter-cell-code PhabricatorMonospaced remarkup-code',
          ),
          array(
            $content,
          )),
        $outputs,
      ),
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

          $raw_data = $data[$image_format];
          if (!is_array($raw_data)) {
            continue;
          }

          $raw_data = implode('', $raw_data);

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
        $content = idx($output, 'text');
        if (!is_array($content)) {
          $content = array();
        }
        $content = implode('', $content);
        break;
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $content);
  }

}
