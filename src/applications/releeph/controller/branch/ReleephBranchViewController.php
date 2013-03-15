<?php

final class ReleephBranchViewController extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();

    $releeph_branch = $this->getReleephBranch();
    $releeph_project = $this->getReleephProject();
    $all_releeph_requests = $releeph_branch->loadReleephRequests(
      $request->getUser());

    $selector = $releeph_project->getReleephFieldSelector();
    $fields = $selector->arrangeFieldsForSelectForm(
      $selector->getFieldSpecifications());

    $form = id(new AphrontFormView())
      ->setMethod('GET')
      ->setUser($request->getUser());

    $filtered_releeph_requests = $all_releeph_requests;
    foreach ($fields as $field) {
      $all_releeph_requests_without_this_field = $all_releeph_requests;
      foreach ($fields as $other_field) {
        if ($other_field != $field) {
          $other_field->selectReleephRequestsHook(
            $request,
            $all_releeph_requests_without_this_field);

        }
      }

      $field->appendSelectControlsHook(
        $form,
        $request,
        $all_releeph_requests,
        $all_releeph_requests_without_this_field);

      $field->selectReleephRequestsHook(
        $request,
        $filtered_releeph_requests);
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue('Filter'));

    $list = id(new ReleephRequestHeaderListView())
      ->setOriginType('branch')
      ->setUser($request->getUser())
      ->setAphrontRequest($this->getRequest())
      ->setReleephProject($releeph_project)
      ->setReleephBranch($releeph_branch)
      ->setReleephRequests($filtered_releeph_requests);

    $filter = id(new AphrontListFilterView())
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($releeph_project->getName())
          ->setHref($releeph_project->getURI()))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($releeph_branch->getDisplayNameWithDetail())
          ->setHref($releeph_branch->getURI()));

    // Don't show the request button for inactive (closed) branches
    if ($releeph_branch->isActive()) {
      $create_uri = $releeph_branch->getURI('request/');
      $crumbs->addAction(
        id(new PhabricatorMenuItemView())
          ->setHref($create_uri)
          ->setName('Request Pick')
          ->setIcon('create'));
    }

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $filter,
        $list
      ),
      array(
        'title' =>
          $releeph_project->getName().
          ' - '.
          $releeph_branch->getDisplayName().
          ' requests'
      ));
  }

}
