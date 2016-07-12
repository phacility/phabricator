<?php

final class PhameBlogFeedController extends PhameBlogController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $posts = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->withVisibility(array(PhameConstants::VISIBILITY_PUBLISHED))
      ->execute();

    $blog_uri = PhabricatorEnv::getProductionURI(
      $this->getApplicationURI('blog/feed/'.$blog->getID().'/'));
    $content = array();
    $content[] = phutil_tag('title', array(), $blog->getName());
    $content[] = phutil_tag('id', array(), $blog_uri);
    $content[] = phutil_tag('link',
      array(
        'rel' => 'self',
        'type' => 'application/atom+xml',
        'href' => $blog_uri,
      ));

    $updated = $blog->getDateModified();
    if ($posts) {
      $updated = max($updated, max(mpull($posts, 'getDateModified')));
    }
    $content[] = phutil_tag('updated', array(), date('c', $updated));

    $description = $blog->getDescription();
    if ($description != '') {
      $content[] = phutil_tag('subtitle', array(), $description);
    }

    $engine = id(new PhabricatorMarkupEngine())->setViewer($viewer);
    foreach ($posts as $post) {
      $engine->addObject($post, PhamePost::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $blogger_phids = mpull($posts, 'getBloggerPHID');
    $bloggers = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($blogger_phids)
      ->execute();

    foreach ($posts as $post) {
      $content[] = hsprintf('<entry>');
      $content[] = phutil_tag('title', array(), $post->getTitle());
      $content[] = phutil_tag('link', array('href' => $post->getLiveURI()));

      $content[] = phutil_tag('id', array(), PhabricatorEnv::getProductionURI(
        '/phame/post/view/'.$post->getID().'/'));

      $content[] = hsprintf(
        '<author><name>%s</name></author>',
        $bloggers[$post->getBloggerPHID()]->getFullName());

      $content[] = phutil_tag(
        'updated',
        array(),
        date('c', $post->getDateModified()));

      $content[] = hsprintf(
        '<content type="xhtml">'.
        '<div xmlns="http://www.w3.org/1999/xhtml">%s</div>'.
        '</content>',
        $engine->getOutput($post, PhamePost::MARKUP_FIELD_BODY));

      $content[] = hsprintf('</entry>');
    }

    $content = phutil_tag(
      'feed',
      array('xmlns' => 'http://www.w3.org/2005/Atom'),
      $content);

    return id(new AphrontFileResponse())
      ->setMimeType('application/xml')
      ->setContent($content);
  }

}
