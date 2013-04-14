<?php

/**
 * @group phame
 */
final class PhamePostListController extends PhameController {

  private $bloggername;
  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter', 'blogger');
    $this->bloggername = idx($data, 'bloggername');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new PhamePostQuery())
      ->setViewer($user);

    $nav = $this->renderSideNavFilterView();

    switch ($this->filter) {
      case 'draft':
        $query->withBloggerPHIDs(array($user->getPHID()));
        $query->withVisibility(PhamePost::VISIBILITY_DRAFT);
        $nodata = pht('You have no unpublished drafts.');
        $title = pht('Unpublished Drafts');
        $nav->selectFilter('post/draft');
        break;
      case 'blogger':
        if ($this->bloggername) {
          $blogger = id(new PhabricatorUser())->loadOneWhere(
            'username = %s',
            $this->bloggername);
          if (!$blogger) {
            return new Aphront404Response();
          }
        } else {
          $blogger = $user;
        }

        $query->withBloggerPHIDs(array($blogger->getPHID()));
        if ($blogger->getPHID() == $user->getPHID()) {
          $nav->selectFilter('post');
          $nodata = pht('You have not written any posts.');
        } else {
          $nodata = pht('%s has not written any posts.', $blogger);
        }
        $title = pht('Posts By %s', $blogger);
        break;
      case 'all':
        $nodata = pht('There are no visible posts.');
        $title = pht('Posts');
        $nav->selectFilter('post/all');
        break;
      default:
        throw new Exception("Unknown filter '{$this->filter}'!");
    }

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = $query->executeWithCursorPager($pager);

    $handle_phids = array_merge(
      mpull($posts, 'getBloggerPHID'),
      mpull($posts, 'getBlogPHID'));
    $this->loadHandles($handle_phids);

    $post_list = $this->renderPostList($posts, $user, $nodata);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI()));

    $nav->appendChild(
      array(
        $crumbs,
        $post_list,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $title,
        'device'  => true,
        'dust'    => true,
      ));
  }


}
