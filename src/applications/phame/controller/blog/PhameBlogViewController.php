<?php

final class PhameBlogViewController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->executeWithCursorPager($pager);

    $nav = $this->renderSideNavFilterView(null);

    $header = id(new PHUIHeaderView())
      ->setHeader($blog->getName())
      ->setUser($user)
      ->setPolicyObject($blog);

    $actions = $this->renderActions($blog, $user);
    $properties = $this->renderProperties($blog, $user, $actions);
    $post_list = $this->renderPostList(
      $posts,
      $user,
      pht('This blog has no visible posts.'));

    require_celerity_resource('phame-css');
    $post_list = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_LARGE)
      ->addClass('phame-post-list')
      ->appendChild($post_list);


    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($blog->getName(), $this->getApplicationURI());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $nav->appendChild(
      array(
        $crumbs,
        $object_box,
        $post_list,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $blog->getName(),
      ));
  }

  private function renderProperties(
    PhameBlog $blog,
    PhabricatorUser $user,
    PhabricatorActionListView $actions) {

    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips');

    $properties = new PHUIPropertyListView();
    $properties->setActionList($actions);

    $properties->addProperty(
      pht('Skin'),
      $blog->getSkin());

    $properties->addProperty(
      pht('Domain'),
      $blog->getDomain());

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
      $user,
      $blog);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $properties->addProperty(
      pht('Joinable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_JOIN]);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user)
      ->addObject($blog, PhameBlog::MARKUP_FIELD_DESCRIPTION)
      ->process();

    $properties->addTextContent(
      phutil_tag(
        'div',
        array(
          'class' => 'phabricator-remarkup',
        ),
        $engine->getOutput($blog, PhameBlog::MARKUP_FIELD_DESCRIPTION)));

    return $properties;
  }

  private function renderActions(PhameBlog $blog, PhabricatorUser $user) {
    $actions = id(new PhabricatorActionListView())
      ->setObject($blog)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($user);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $blog,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_join = PhabricatorPolicyFilter::hasCapability(
      $user,
      $blog,
      PhabricatorPolicyCapability::CAN_JOIN);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-plus')
        ->setHref($this->getApplicationURI('post/edit/?blog='.$blog->getID()))
        ->setName(pht('Write Post'))
        ->setDisabled(!$can_join)
        ->setWorkflow(!$can_join));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setIcon('fa-globe')
        ->setHref($blog->getLiveURI())
        ->setName(pht('View Live')));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('blog/edit/'.$blog->getID().'/'))
        ->setName(pht('Edit Blog'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI('blog/delete/'.$blog->getID().'/'))
        ->setName(pht('Delete Blog'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $actions;
  }

}
