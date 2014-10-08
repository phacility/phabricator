<?php

final class DiffusionCommitTagsController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $data['user'] = $this->getRequest()->getUser();
    $this->diffusionRequest = DiffusionRequest::newFromDictionary($data);
  }

  public function processRequest() {
    $request = $this->getDiffusionRequest();
    $tag_limit = 10;

    $tags = array();
    try {
      $tags = DiffusionRepositoryTag::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.tagsquery',
          array(
            'commit' => $request->getCommit(),
            'limit' => $tag_limit + 1,
          )));
    } catch (ConduitException $ex) {
      if ($ex->getMessage() != 'ERR-UNSUPPORTED-VCS') {
        throw $ex;
      }
    }

    $has_more_tags = (count($tags) > $tag_limit);
    $tags = array_slice($tags, 0, $tag_limit);

    $tag_links = array();
    foreach ($tags as $tag) {
      $tag_links[] = phutil_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'browse',
              'commit'  => $tag->getName(),
            )),
        ),
        $tag->getName());
    }

    if ($has_more_tags) {
      $tag_links[] = phutil_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'tags',
            )),
        ),
        pht("More Tags\xE2\x80\xA6"));
    }

    return id(new AphrontAjaxResponse())
      ->setContent($tag_links ? implode(', ', $tag_links) : pht('None'));
  }
}
