<?php

abstract class PhameLiveController extends PhameController {

  private $isExternal;
  private $isLive;
  private $blog;
  private $post;

  public function shouldAllowPublic() {
    return true;
  }

  protected function getIsExternal() {
    return $this->isExternal;
  }

  protected function getIsLive() {
    return $this->isLive;
  }

  protected function getBlog() {
    return $this->blog;
  }

  protected function getPost() {
    return $this->post;
  }

  protected function setupLiveEnvironment() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $site = $request->getSite();
    $blog_id = $request->getURIData('blogID');
    $post_id = $request->getURIData('id');

    if ($site instanceof PhameBlogSite) {
      // This is a live page on a custom domain. We already looked up the blog
      // in the Site handler by examining the domain, so we don't need to do
      // more lookups.

      $blog = $site->getBlog();
      $is_external = true;
      $is_live = true;
    } else if ($blog_id) {
      // This is a blog detail view, an internal blog live view, or an
      // internal post live view The internal post detail view is handled
      // below.

      $is_external = false;
      if ($request->getURIData('live')) {
        $is_live = true;
      } else {
        $is_live = false;
      }

      $blog_query = id(new PhameBlogQuery())
        ->setViewer($viewer)
        ->needProfileImage(true)
        ->withIDs(array($blog_id));

      // If this is a live view, only show active blogs.
      if ($is_live) {
        $blog_query->withStatuses(
          array(
            PhameBlog::STATUS_ACTIVE,
          ));
      }

      $blog = $blog_query->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }

    } else {
      // This is a post detail page, so we'll figure out the blog by loading
      // the post first.
      $is_external = false;
      $is_live = false;
      $blog = null;
    }

    if ($post_id) {
      $post_query = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withIDs(array($post_id));

      if ($blog) {
        $post_query->withBlogPHIDs(array($blog->getPHID()));
      }

      // Only show published posts on external domains.
      if ($is_external) {
        $post_query->withVisibility(PhameConstants::VISIBILITY_PUBLISHED);
      }

      $post = $post_query->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }

      // If this is a post detail page, the URI didn't come with a blog ID,
      // so fill that in.
      if (!$blog) {
        $blog = $post->getBlog();
      }
    } else {
      $post = null;
    }

    $this->isExternal = $is_external;
    $this->isLive = $is_live;
    $this->blog = $blog;
    $this->post = $post;

    // If we have a post, canonicalize the URI to the post's current slug and
    // redirect the user if it isn't correct.
    if ($post) {
      $slug = $request->getURIData('slug');
      if ($post->getSlug() != $slug) {
        if ($is_live) {
          if ($is_external) {
            $uri = $post->getExternalLiveURI();
          } else {
            $uri = $post->getInternalLiveURI();
          }
        } else {
          $uri = $post->getViewURI();
        }

        $response = id(new AphrontRedirectResponse())
          ->setURI($uri);

        if ($is_external) {
          $response->setIsExternal(true);
        }

        return $response;
      }
    }

    return null;
  }

  protected function buildApplicationCrumbs() {
    $blog = $this->getBlog();
    $post = $this->getPost();

    $is_live = $this->getIsLive();
    $is_external = $this->getIsExternal();

    // If this is an external view, don't put the "Phame" crumb or the
    // "Blogs" crumb into the crumbs list.
    if ($is_external) {
      $crumbs = new PHUICrumbsView();
    } else {
      $crumbs = parent::buildApplicationCrumbs();
      $crumbs->addTextCrumb(
        pht('Blogs'),
        $this->getApplicationURI('blog/'));
    }

    $crumbs->setBorder(true);

    if ($blog) {
      if ($post) {
        if ($is_live) {
          if ($is_external) {
            $blog_uri = $blog->getExternalLiveURI();
          } else {
            $blog_uri = $blog->getInternalLiveURI();
          }
        } else {
          $blog_uri = $blog->getViewURI();
        }
      } else {
        $blog_uri = null;
      }

      $crumbs->addTextCrumb($blog->getName(), $blog_uri);
    }

    if ($post) {
      $crumbs->addTextCrumb($post->getTitle());
    }

    return $crumbs;
  }

}
