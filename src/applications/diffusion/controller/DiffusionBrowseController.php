<?php

final class DiffusionBrowseController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $results = $browse_query->loadPaths();

    $content = array();

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    if ($drequest->getTagContent()) {
      $title = 'Tag: '.$drequest->getSymbolicCommit();

      $tag_view = new AphrontPanelView();
      $tag_view->setHeader(phutil_escape_html($title));
      $tag_view->appendChild(
        $this->markupText($drequest->getTagContent()));

      $content[] = $tag_view;
    }

    if (!$results) {

      if ($browse_query->getReasonForEmptyResultSet() ==
          DiffusionBrowseQuery::REASON_IS_FILE) {
        $controller = new DiffusionBrowseFileController($this->getRequest());
        $controller->setDiffusionRequest($drequest);
        return $this->delegateToController($controller);
      }

      $empty_result = new DiffusionEmptyResultView();
      $empty_result->setDiffusionRequest($drequest);
      $empty_result->setBrowseQuery($browse_query);
      $empty_result->setView($this->getRequest()->getStr('view'));
      $content[] = $empty_result;

    } else {

      $readme = null;

      $phids = array();
      foreach ($results as $result) {
        $data = $result->getLastCommitData();
        if ($data) {
          if ($data->getCommitDetail('authorPHID')) {
            $phids[$data->getCommitDetail('authorPHID')] = true;
          }
        }

        $path = $result->getPath();
        if (preg_match('/^readme(|\.txt|\.remarkup)$/i', $path)) {
          $readme = $result;
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

    return $this->buildStandardPageResponse(
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
    $text = $engine->markupText($text);

    $text = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $text);

    return $text;
  }

}
