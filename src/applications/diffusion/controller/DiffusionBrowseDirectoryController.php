<?php

final class DiffusionBrowseDirectoryController
  extends DiffusionBrowseController {

  private $browseQueryResults;

  public function setBrowseQueryResults(DiffusionBrowseResultSet $results) {
    $this->browseQueryResults = $results;
    return $this;
  }

  public function getBrowseQueryResults() {
    return $this->browseQueryResults;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->diffusionRequest;

    $results = $this->getBrowseQueryResults();
    $reason = $results->getReasonForEmptyResultSet();

    $content = array();
    $actions = $this->buildActionView($drequest);
    $properties = $this->buildPropertyView($drequest, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderView($drequest))
      ->addPropertyList($properties);

    $content[] = $object_box;
    $content[] = $this->renderSearchForm($collapsed = true);

    if (!$results->isValidResults()) {
      $empty_result = new DiffusionEmptyResultView();
      $empty_result->setDiffusionRequest($drequest);
      $empty_result->setDiffusionBrowseResultSet($results);
      $empty_result->setView($request->getStr('view'));
      $content[] = $empty_result;
    } else {
      $phids = array();
      foreach ($results->getPaths() as $result) {
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
      $browse_table->setPaths($results->getPaths());
      $browse_table->setUser($request->getUser());

      $browse_panel = new PHUIObjectBoxView();
      $browse_panel->setHeaderText($drequest->getPath(), '/');
      $browse_panel->appendChild($browse_table);

      $content[] = $browse_panel;
    }

    $content[] = $this->buildOpenRevisions();


    $readme_path = $results->getReadmePath();
    if ($readme_path) {
      $readme_content = $this->callConduitWithDiffusionRequest(
        'diffusion.filecontentquery',
        array(
          'path' => $readme_path,
          'commit' => $drequest->getStableCommit(),
        ));
      if ($readme_content) {
        $content[] = id(new DiffusionReadmeView())
          ->setUser($this->getViewer())
          ->setPath($readme_path)
          ->setContent($readme_content['corpus']);
      }
    }

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => array(
          nonempty(basename($drequest->getPath()), '/'),
          $drequest->getRepository()->getCallsign().' Repository',
        ),
      ));
  }

}
