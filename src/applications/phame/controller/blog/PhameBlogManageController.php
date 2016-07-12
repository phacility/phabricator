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
      ->needHeaderImage(true)
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

    $view = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Live'))
      ->setIcon('fa-external-link')
      ->setHref($blog->getLiveURI())
      ->setDisabled($blog->isArchived());

    $header = id(new PHUIHeaderView())
      ->setHeader($blog->getName())
      ->setUser($viewer)
      ->setPolicyObject($blog)
      ->setImage($picture)
      ->setStatus($header_icon, $header_color, $header_name)
      ->addActionLink($view);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blog,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $header->setImageEditURL(
        $this->getApplicationURI('blog/picture/'.$blog->getID().'/'));
    }

    $curtain = $this->buildCurtain($blog);
    $properties = $this->buildPropertyView($blog);
    $file = $this->buildFileView($blog);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Blogs'),
      $this->getApplicationURI('blog/'));
    $crumbs->addTextCrumb(
      $blog->getName(),
      $this->getApplicationURI('blog/view/'.$id));
    $crumbs->addTextCrumb(pht('Manage Blog'));
    $crumbs->setBorder(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $blog,
      new PhameBlogTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->addPropertySection(pht('Header'), $file)
      ->setMainColumn(
        array(
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($blog->getName())
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildPropertyView(PhameBlog $blog) {
    $viewer = $this->getViewer();

    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips');

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $full_domain = $blog->getDomainFullURI();
    if (!$full_domain) {
      $full_domain = phutil_tag('em', array(), pht('No external domain'));
    }
    $properties->addProperty(pht('Full Domain'), $full_domain);

    $parent_site = $blog->getParentSite();
    if (!$parent_site) {
      $parent_site = phutil_tag('em', array(), pht('No parent site'));
    }

    $properties->addProperty(pht('Parent Site'), $parent_site);

    $parent_domain = $blog->getParentDomain();
    if (!$parent_domain) {
      $parent_domain = phutil_tag('em', array(), pht('No parent domain'));
    }

    $properties->addProperty(pht('Parent Domain'), $parent_domain);

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

  private function buildCurtain(PhameBlog $blog) {
    $viewer = $this->getViewer();

    $curtain = $this->newCurtainView($blog);

    $actions = id(new PhabricatorActionListView())
      ->setObject($blog)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blog,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('blog/edit/'.$blog->getID().'/'))
        ->setName(pht('Edit Blog'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-camera')
        ->setHref($this->getApplicationURI('blog/header/'.$blog->getID().'/'))
        ->setName(pht('Edit Blog Header'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-picture-o')
        ->setHref($this->getApplicationURI('blog/picture/'.$blog->getID().'/'))
        ->setName(pht('Edit Blog Picture'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($blog->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Blog'))
          ->setIcon('fa-check')
          ->setHref(
            $this->getApplicationURI('blog/archive/'.$blog->getID().'/'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Blog'))
          ->setIcon('fa-ban')
          ->setHref(
            $this->getApplicationURI('blog/archive/'.$blog->getID().'/'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $curtain;
  }

  private function buildFileView(
    PhameBlog $blog) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if ($blog->getHeaderImagePHID()) {
      $view->addImageContent(
        phutil_tag(
          'img',
          array(
            'src'     => $blog->getHeaderImageURI(),
            'class'   => 'phabricator-image-macro-hero',
          )));
      return $view;
    }
    return null;
  }

}
