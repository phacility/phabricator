<?php

final class PhameHomeController extends PhamePostController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withVisibility(PhameConstants::VISIBILITY_PUBLISHED)
      ->executeWithCursorPager($pager);

    $actions = $this->renderActions($viewer);
    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Search'))
      ->setHref('#')
      ->setIconFont('fa-search')
      ->addClass('phui-mobile-menu')
      ->setDropdownMenu($actions);

    $title = pht('Recent Posts');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->addActionLink($action_button);

    $post_list = id(new PhamePostListView())
      ->setPosts($posts)
      ->setViewer($viewer)
      ->showBlog(true)
      ->setNodata(pht('No Recent Visible Posts.'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(
      pht('Recent Posts'),
      $this->getApplicationURI('post/'));

    $page = id(new PHUIDocumentViewPro())
      ->setHeader($header)
      ->appendChild($post_list);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $page,
      ));


  }

  private function renderActions($viewer) {
    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil-square-o')
        ->setHref($this->getApplicationURI('post/'))
        ->setName(pht('Find Posts')));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-star')
        ->setHref($this->getApplicationURI('blog/'))
        ->setName(pht('Find Blogs')));

    return $actions;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      PhameBlogCreateCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blog'))
        ->setHref($this->getApplicationURI('/blog/new/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

}
