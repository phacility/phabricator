<?php

final class PhameCreatePostConduitAPIMethod extends PhameConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phame.createpost';
  }

  public function getMethodDescription() {
    return pht('Create a phame post.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  protected function defineParamTypes() {
    return array(
      'blogPHID'      => 'required phid',
      'title'         => 'required string',
      'body'          => 'required string',
      'phameTitle'    => 'optional string',
      'bloggerPHID'   => 'optional phid',
      'isDraft'       => 'optional bool',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' =>
        pht('Missing or malformed parameter.'),
      'ERR-INVALID-BLOG'      =>
        pht('Invalid blog PHID or user can not post to blog.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();
    $blog_phid = $request->getValue('blogPHID');
    $title = $request->getValue('title');
    $body = $request->getValue('body');
    $exception_description = array();
    if (!$blog_phid) {
      $exception_description[] = pht('No blog phid.');
    }
    if (!strlen($title)) {
      $exception_description[] = pht('No post title.');
    }
    if (!strlen($body)) {
      $exception_description[] = pht('No post body.');
    }
    if ($exception_description) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(implode("\n", $exception_description));
    }

    $blogger_phid = $request->getValue('bloggerPHID');
    if ($blogger_phid) {
      $blogger = id(new PhabricatorPeopleQuery())
        ->setViewer($user)
        ->withPHIDs(array($blogger_phid))
        ->executeOne();
    } else {
      $blogger = $user;
    }

    $blog = id(new PhameBlogQuery())
      ->setViewer($blogger)
      ->withPHIDs(array($blog_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_JOIN,
        ))
      ->executeOne();

    if (!$blog) {
      throw new ConduitException('ERR-INVALID-BLOG');
    }

    $post = PhamePost::initializePost($blogger, $blog);
    $is_draft = $request->getValue('isDraft', false);
    if (!$is_draft) {
      $post->setDatePublished(time());
      $post->setVisibility(PhamePost::VISIBILITY_PUBLISHED);
    }
    $post->setTitle($title);
    $phame_title = $request->getValue(
      'phameTitle',
      id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(64)
      ->truncateString($title));
    $post->setPhameTitle(PhabricatorSlug::normalize($phame_title));
    $post->setBody($body);
    $post->save();

    return $post->toDictionary();
  }

}
