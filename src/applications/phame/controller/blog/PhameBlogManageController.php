<?php

final class PhameBlogManageController extends PhameBlogController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfileImage(true)
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    if ($blog->isArchived()) {
      $header_icon = 'fa-ban';
      $header_name = pht('Archived');
      $header_color = 'dark';
    } else {
      $header_icon = 'fa-check';
      $header_name = pht('Active');
      $header_color = 'bluegrey';
    }

    $picture = $blog->getProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader($blog->getName())
      ->setUser($viewer)
      ->setPolicyObject($blog)
      ->setImage($picture)
      ->setStatus($header_icon, $header_color, $header_name);

    $actions = $this->renderActions($blog, $viewer);
    $properties = $this->renderProperties($blog, $viewer, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Blogs'),
      $this->getApplicationURI('blog/'));
    $crumbs->addTextCrumb(
      $blog->getName());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $blog,
      new PhameBlogTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->newPage()
      ->setTitle($blog->getName())
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $object_box,
          $timeline,
      ));
  }

  private function renderProperties(
    PhameBlog $blog,
    PhabricatorUser $viewer,
    PhabricatorActionListView $actions) {

    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips');

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($blog)
      ->setActionList($actions);

    $domain = $blog->getDomain();
    if (!$domain) {
      $domain = phutil_tag('em', array(), pht('No external domain'));
    }

    $properties->addProperty(pht('Domain'), $domain);

    $feed_uri = PhabricatorEnv::getProductionURI(
      $this->getApplicationURI('blog/feed/'.$blog->getID().'/'));
    $properties->addProperty(
      pht('Atom URI'),
      javelin_tag('a',
        array(
          'href' => $feed_uri,
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => pht('Atom URI does not support custom domains.'),
            'size' => 320,
          ),
        ),
        $feed_uri));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $blog);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->addObject($blog, PhameBlog::MARKUP_FIELD_DESCRIPTION)
      ->process();

    $properties->invokeWillRenderEvent();

    $description = $blog->getDescription();
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $properties->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $properties->addTextContent($description);
    }

    return $properties;
  }

  private function renderActions(PhameBlog $blog, PhabricatorUser $viewer) {
    $actions = id(new PhabricatorActionListView())
      ->setObject($blog)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blog,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('blog/edit/'.$blog->getID().'/'))
        ->setName(pht('Edit Blog'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-picture-o')
        ->setHref($this->getApplicationURI('blog/picture/'.$blog->getID().'/'))
        ->setName(pht('Edit Blog Picture'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($blog->isArchived()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Blog'))
          ->setIcon('fa-check')
          ->setHref(
            $this->getApplicationURI('blog/archive/'.$blog->getID().'/'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Blog'))
          ->setIcon('fa-ban')
          ->setHref(
            $this->getApplicationURI('blog/archive/'.$blog->getID().'/'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $actions;
  }

}
