<?php

final class PhamePostNotLiveController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($id))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $reasons = array();
    if (!$post->getBlog()) {
      $reasons[] = phutil_tag('p', array(), pht(
        'You can not view the live version of this post because it '.
        'is not associated with a blog. Move the post to a blog in order to '.
        'view it live.'));
    }

    if ($post->isDraft()) {
      $reasons[] = phutil_tag('p', array(), pht(
        'You can not view the live version of this post because it '.
        'is still a draft. Use "Preview/Publish" to publish the post.'));
    }

    if ($reasons) {
      $cancel_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Post Not Live'))
        ->addCancelButton($cancel_uri);

      foreach ($reasons as $reason) {
        $dialog->appendChild($reason);
      }

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    // No reason this can't go live, maybe an old link. Kick them live and see
    // what happens.
    $live_uri = $post->getViewURI();
    return id(new AphrontRedirectResponse())->setURI($live_uri);
  }

}
