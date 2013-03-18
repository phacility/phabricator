<?php

final class ReleephRequestViewController extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();

    $uri_path = $request->getRequestURI()->getPath();
    $legacy_prefix = '/releeph/request/';
    if (strncmp($uri_path, $legacy_prefix, strlen($legacy_prefix)) === 0) {
      return id(new AphrontRedirectResponse())
        ->setURI('/RQ'.$this->getReleephRequest()->getID());
    }

    $releeph_request = $this->getReleephRequest();
    $releeph_branch  = $this->getReleephBranch();
    $releeph_project = $this->getReleephProject();

    $releeph_branch->populateReleephRequestHandles(
      $request->getUser(), array($releeph_request));

    $rq_view =
      id(new ReleephRequestHeaderListView())
        ->setReleephProject($releeph_project)
        ->setReleephBranch($releeph_branch)
        ->setReleephRequests(array($releeph_request))
        ->setUser($request->getUser())
        ->setAphrontRequest($this->getRequest())
        ->setReloadOnStateChange(true)
        ->setOriginType('request');

    $events = $releeph_request->loadEvents();
    $phids = array_mergev(mpull($events, 'extractPHIDs'));
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $rq_event_list_view =
      id(new ReleephRequestEventListView())
        ->setUser($request->getUser())
        ->setEvents($events)
        ->setHandles($handles);

    // Handle comment submit
    $origin_uri = '/RQ'.$releeph_request->getID();
    if ($request->isFormPost()) {
      id(new ReleephRequestEditor($releeph_request))
        ->setActor($request->getUser())
        ->addComment($request->getStr('comment'));
      return id(new AphrontRedirectResponse())->setURI($origin_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('comment'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($origin_uri, 'Cancel')
          ->setValue("Submit"));

    $rq_comment_form = id(new AphrontPanelView())
      ->setHeader('Add a comment')
      ->setWidth(AphrontPanelView::WIDTH_FULL)
      ->appendChild($form);

    $title = hsprintf("RQ%d: %s",
      $releeph_request->getID(),
      $releeph_request->getSummaryForDisplay());

    $crumbs = $this->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($releeph_project->getName())
          ->setHref($releeph_project->getURI()))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($releeph_branch->getDisplayNameWithDetail())
          ->setHref($releeph_branch->getURI()))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('RQ'.$releeph_request->getID())
          ->setHref('/RQ'.$releeph_request->getID()));

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        array(
          $rq_view,
          $rq_event_list_view,
          $rq_comment_form
        )
      ),
      array(
        'title' => $title
      ));
  }
}
