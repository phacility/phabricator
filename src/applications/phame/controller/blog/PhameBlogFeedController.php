<?php

/**
 * @group phame
 */
final class PhameBlogFeedController extends PhameController {

  private $id;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $posts = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->withVisibility(PhamePost::VISIBILITY_PUBLISHED)
      ->execute();

    $content = array();
    $content[] = phutil_tag('title', array(), $blog->getName());
    $content[] = phutil_tag('id', array(), PhabricatorEnv::getProductionURI(
      '/phame/blog/view/'.$blog->getID().'/'));

    $updated = $blog->getDateModified();
    if ($posts) {
      $updated = max($updated, max(mpull($posts, 'getDateModified')));
    }
    $content[] = phutil_tag('updated', array(), date('c', $updated));

    $description = $blog->getDescription();
    if ($description != '') {
      $content[] = phutil_tag('subtitle', array(), $description);
    }

    $engine = id(new PhabricatorMarkupEngine())->setViewer($user);
    foreach ($posts as $post) {
      $engine->addObject($post, PhamePost::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $bloggers = mpull($posts, 'getBloggerPHID');
    $bloggers = id(new PhabricatorObjectHandleData($bloggers))
      ->setViewer($user)
      ->loadHandles();

    foreach ($posts as $post) {
      $content[] = hsprintf('<entry>');
      $content[] = phutil_tag('title', array(), $post->getTitle());
      $content[] = phutil_tag('link', array('href' => $post->getViewURI()));

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
