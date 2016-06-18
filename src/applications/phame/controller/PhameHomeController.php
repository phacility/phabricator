<?php

final class PhameHomeController extends PhamePostController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhameBlogEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $blogs = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withStatuses(array(PhameBlog::STATUS_ACTIVE))
      ->needProfileImage(true)
      ->execute();

    $post_list = null;
    if ($blogs) {
      $blog_phids = mpull($blogs, 'getPHID');

      $pager = id(new AphrontCursorPagerView())
        ->readFromRequest($request);

      $posts = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withBlogPHIDs($blog_phids)
        ->withVisibility(array(PhameConstants::VISIBILITY_PUBLISHED))
        ->setOrder('datePublished')
        ->executeWithCursorPager($pager);

      if ($posts) {
        $post_list = id(new PhamePostListView())
          ->setPosts($posts)
          ->setViewer($viewer)
          ->showBlog(true);
      } else {
        $post_list = id(new PHUIBigInfoView())
          ->setIcon('fa-star')
          ->setTitle('No Visible Posts')
          ->setDescription(
            pht('There aren\'t any visible blog posts.'));
      }
    } else {
      $create_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Create a Blog'))
        ->setHref('/phame/blog/edit/')
        ->setColor(PHUIButtonView::GREEN);

      $post_list = id(new PHUIBigInfoView())
        ->setIcon('fa-star')
        ->setTitle('Welcome to Phame')
        ->setDescription(
          pht('There aren\'t any visible blog posts.'))
        ->addAction($create_button);
    }

    $view_all = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View All'))
      ->setHref($this->getApplicationURI('post/'))
      ->setIcon('fa-list-ul');

    $title = pht('Recent Posts');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->addActionLink($view_all);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(
      pht('Recent Posts'),
      $this->getApplicationURI('post/'));

    $page = id(new PHUIDocumentViewPro())
      ->setHeader($header)
      ->appendChild($post_list);

    $blog_list = id(new PhameBlogListView())
      ->setBlogs($blogs)
      ->setViewer($viewer);

    $draft_list = null;
    if ($viewer->isLoggedIn() && $blogs) {
      $drafts = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withBloggerPHIDs(array($viewer->getPHID()))
        ->withBlogPHIDs(mpull($blogs, 'getPHID'))
        ->withVisibility(array(PhameConstants::VISIBILITY_DRAFT))
        ->setLimit(5)
        ->execute();

      $draft_list = id(new PhameDraftListView())
        ->setPosts($drafts)
        ->setBlogs($blogs)
        ->setViewer($viewer);
    }

    $phame_view = id(new PHUITwoColumnView())
      ->setMainColumn(array(
        $page,
      ))
      ->setSideColumn(array(
        $blog_list,
        $draft_list,
      ))
      ->addClass('phame-home-container');

    $phame_home = phutil_tag_div('phame-home-view', $phame_view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $phame_home,
      ));
  }

}
