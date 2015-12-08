<?php

final class PhamePostNotLiveController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $reasons = array();
    if ($post->isDraft()) {
      $reasons[] = phutil_tag('p', array(), pht(
        'You can not view the live version of this post because it '.
        'is still a draft. Use "Preview" or "Publish" to publish the post.'));
    }

    if ($reasons) {
      $cancel_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Post Not Live'))
        ->addCancelButton($cancel_uri);

      foreach ($reasons as $reason) {
        $dialog->appendChild($reason);
      }

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    // No reason this can't go live, maybe an old link. Kick them live and see
    // what happens.
    $live_uri = $post->getLiveURI();
    return id(new AphrontRedirectResponse())->setURI($live_uri);
  }

}
