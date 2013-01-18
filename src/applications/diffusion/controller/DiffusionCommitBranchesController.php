<?php

final class DiffusionCommitBranchesController extends DiffusionController {

  public function willProcessRequest(array $data) {
    $this->diffusionRequest = DiffusionRequest::newFromDictionary($data);
  }

  public function processRequest() {
    $request = $this->getDiffusionRequest();

    $branch_query = DiffusionContainsQuery::newFromDiffusionRequest($request);
    $branches = $branch_query->loadContainingBranches();

    $branch_links = array();
    foreach ($branches as $branch => $commit) {
      $branch_links[] = phutil_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'browse',
              'branch'  => $branch,
            )),
        ),
        $branch);
    }

    return id(new AphrontAjaxResponse())
      ->setContent($branch_links ? implode(', ', $branch_links) : 'None');
  }
}
