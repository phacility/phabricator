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
      ->withPublishedAfter(strtotime('-1 month'))
      ->execute();

    $content = array();
    $content[] = '<feed xmlns="http://www.w3.org/2005/Atom">';
    $content[] = '<title>'.phutil_escape_html($blog->getName()).'</title>';
    $content[] = '<id>'.phutil_escape_html(PhabricatorEnv::getProductionURI(
      '/phame/blog/view/'.$blog->getID().'/')).'</id>';

    $updated = $blog->getDateModified();
    if ($posts) {
      $updated = max($updated, max(mpull($posts, 'getDateModified')));
    }
    $content[] = '<updated>'.date('c', $updated).'</updated>';

    $description = $blog->getDescription();
    if ($description != '') {
      $content[] = '<subtitle>'.phutil_escape_html($description).'</subtitle>';
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
      $content[] = '<entry>';
      $content[] = '<title>'.phutil_escape_html($post->getTitle()).'</title>';
      $content[] = '<link href="'.phutil_escape_html($post->getViewURI()).'"/>';

      $content[] = '<id>'.phutil_escape_html(PhabricatorEnv::getProductionURI(
        '/phame/post/view/'.$post->getID().'/')).'</id>';

      $content[] =
        '<author>'.
        '<name>'.
        phutil_escape_html($bloggers[$post->getBloggerPHID()]->getFullName()).
        '</name>'.
        '</author>';

      $content[] = '<updated>'.date('c', $post->getDateModified()).'</updated>';

      $content[] =
        '<content type="xhtml">'.
        '<div xmlns="http://www.w3.org/1999/xhtml">'.
        $engine->getOutput($post, PhamePost::MARKUP_FIELD_BODY).
        '</div>'.
        '</content>';

      $content[] = '</entry>';
    }

    $content[] = '</feed>';

    return id(new AphrontFileResponse())
      ->setMimeType('application/xml')
      ->setContent(implode('', $content));
  }

}
