<?php

final class DoorkeeperTagsController extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $tags = $request->getStr('tags');
    try {
      $tags = phutil_json_decode($tags);
    } catch (PhutilJSONParserException $ex) {
      $tags = array();
    }

    $refs = array();
    foreach ($tags as $key => $tag_spec) {
      $tag = $tag_spec['ref'];
      $ref = id(new DoorkeeperObjectRef())
        ->setApplicationType($tag[0])
        ->setApplicationDomain($tag[1])
        ->setObjectType($tag[2])
        ->setObjectID($tag[3]);
      $refs[$key] = $ref;
    }

    $refs = id(new DoorkeeperImportEngine())
      ->setViewer($viewer)
      ->setRefs($refs)
      ->setTimeout(15)
      ->execute();

    $results = array();
    foreach ($refs as $key => $ref) {
      if (!$ref->getIsVisible()) {
        continue;
      }

      $uri = $ref->getExternalObject()->getObjectURI();
      if (!$uri) {
        continue;
      }

      $tag_spec = $tags[$key];

      $id = $tag_spec['id'];
      $view = idx($tag_spec, 'view');

      $is_short = ($view == 'short');

      if ($is_short) {
        $name = $ref->getShortName();
      } else {
        $name = $ref->getFullName();
      }

      $tag = id(new PHUITagView())
        ->setID($id)
        ->setName($name)
        ->setHref($uri)
        ->setType(PHUITagView::TYPE_OBJECT)
        ->setExternal(true)
        ->render();

      $results[] = array(
        'id' => $id,
        'markup' => $tag,
      );
    }

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'tags' => $results,
      ));
  }


}
