<?php

final class DiffusionBrowseController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    if ($this->getRequest()->getStr('before')) {
      $results = array();
      $is_file = true;
    } else {
      $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
      $browse_query->setViewer($this->getRequest()->getUser());
      $results = $browse_query->loadPaths();
      $reason = $browse_query->getReasonForEmptyResultSet();
      $is_file = ($reason == DiffusionBrowseQuery::REASON_IS_FILE);
    }

    $content = array();

    if ($drequest->getTagContent()) {
      $title = 'Tag: '.$drequest->getSymbolicCommit();

      $tag_view = new AphrontPanelView();
      $tag_view->setHeader($title);
      $tag_view->appendChild(
        $this->markupText($drequest->getTagContent()));

      $content[] = $tag_view;
    }

    if (!$results) {

      if ($is_file) {
        $controller = new DiffusionBrowseFileController($this->getRequest());
        $controller->setDiffusionRequest($drequest);
        $controller->setCurrentApplication($this->getCurrentApplication());
        return $this->delegateToController($controller);
      }

      $empty_result = new DiffusionEmptyResultView();
      $empty_result->setDiffusionRequest($drequest);
      $empty_result->setBrowseQuery($browse_query);
      $empty_result->setView($this->getRequest()->getStr('view'));
      $content[] = $empty_result;

    } else {

      $phids = array();
      foreach ($results as $result) {
        $data = $result->getLastCommitData();
        if ($data) {
          if ($data->getCommitDetail('authorPHID')) {
            $phids[$data->getCommitDetail('authorPHID')] = true;
          }
        }
      }

      $phids = array_keys($phids);
      $handles = $this->loadViewerHandles($phids);

      $browse_table = new DiffusionBrowseTableView();
      $browse_table->setDiffusionRequest($drequest);
      $browse_table->setHandles($handles);
      $browse_table->setPaths($results);
      $browse_table->setUser($this->getRequest()->getUser());

      $browse_panel = new AphrontPanelView();
      $browse_panel->appendChild($browse_table);
      $browse_panel->setNoBackground();

      $content[] = $browse_panel;
    }

    $content[] = $this->buildOpenRevisions();

    $readme_content = $browse_query->renderReadme($results);
    if ($readme_content) {
      $readme_panel = new AphrontPanelView();
      $readme_panel->setHeader('README');
      $readme_panel->appendChild($readme_content);

      $content[] = $readme_panel;
    }


    $nav = $this->buildSideNav('browse', false);
    $nav->appendChild($content);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => array(
          nonempty(basename($drequest->getPath()), '/'),
          $drequest->getRepository()->getCallsign().' Repository',
        ),
      ));
  }

  private function markupText($text) {
    $engine = PhabricatorMarkupEngine::newDiffusionMarkupEngine();
    $engine->setConfig('viewer', $this->getRequest()->getUser());
    $text = $engine->markupText($text);

    $text = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $text);

    return $text;
  }

}
