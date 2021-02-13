<?php

final class PhabricatorSearchHovercardController
  extends PhabricatorSearchBaseController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $cards = $request->getJSONMap('cards');

    // If object names are provided, look them up and pretend they were
    // passed as additional PHIDs. This is primarily useful for debugging,
    // since you don't have to go look up user PHIDs to preview their
    // hovercards.
    $names = $request->getStrList('names');
    if ($names) {
      $named_objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames($names)
        ->execute();

      foreach ($named_objects as $object) {
        $cards[] = array(
          'objectPHID' => $object->getPHID(),
        );
      }
    }

    $object_phids = array();
    $handle_phids = array();
    foreach ($cards as $card) {
      $object_phid = idx($card, 'objectPHID');

      $handle_phids[] = $object_phid;
      $object_phids[] = $object_phid;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($handle_phids)
      ->execute();

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    $extensions =
      PhabricatorHovercardEngineExtension::getAllEnabledExtensions();

    $extension_maps = array();
    foreach ($extensions as $key => $extension) {
      $extension->setViewer($viewer);

      $extension_phids = array();
      foreach ($objects as $phid => $object) {
        if ($extension->canRenderObjectHovercard($object)) {
          $extension_phids[$phid] = $phid;
        }
      }

      $extension_maps[$key] = $extension_phids;
    }

    $extension_data = array();
    foreach ($extensions as $key => $extension) {
      $extension_phids = $extension_maps[$key];
      if (!$extension_phids) {
        unset($extensions[$key]);
        continue;
      }

      $extension_data[$key] = $extension->willRenderHovercards(
        array_select_keys($objects, $extension_phids));
    }

    $results = array();
    foreach ($cards as $card_key => $card) {
      $object_phid = $card['objectPHID'];

      $handle = $handles[$object_phid];
      $object = idx($objects, $object_phid);

      $hovercard = id(new PHUIHovercardView())
        ->setUser($viewer)
        ->setObjectHandle($handle);

      if ($object) {
        $hovercard->setObject($object);

        foreach ($extension_maps as $key => $extension_phids) {
          if (isset($extension_phids[$phid])) {
            $extensions[$key]->renderHovercard(
              $hovercard,
              $handle,
              $object,
              $extension_data[$key]);
          }
        }
      }

      $results[$card_key] = $hovercard;
    }

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'cards' => $results,
        ));
    }

    foreach ($results as $key => $hovercard) {
      $results[$key] = phutil_tag('div',
        array(
          'class' => 'ml',
        ),
        $hovercard);
    }

    return $this->newPage()
      ->appendChild($results)
      ->setShowFooter(false);
  }

}
