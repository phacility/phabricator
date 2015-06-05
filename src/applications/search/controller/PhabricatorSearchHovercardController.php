<?php

final class PhabricatorSearchHovercardController
  extends PhabricatorSearchBaseController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $phids = $request->getArr('phids');

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    $cards = array();

    foreach ($phids as $phid) {
      $handle = $handles[$phid];
      $object = $objects[$phid];

      $hovercard = id(new PhabricatorHovercardView())
        ->setUser($viewer)
        ->setObjectHandle($handle);

      if ($object) {
        $hovercard->setObject($object);
      }

      // Send it to the other side of the world, thanks to PhutilEventEngine
      $event = new PhabricatorEvent(
        PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD,
        array(
          'hovercard' => $hovercard,
          'handle'    => $handle,
          'object'    => $object,
        ));
      $event->setUser($viewer);
      PhutilEventEngine::dispatchEvent($event);

      $cards[$phid] = $hovercard;
    }

    // Browser-friendly for non-Ajax requests
    if (!$request->isAjax()) {
      foreach ($cards as $key => $hovercard) {
        $cards[$key] = phutil_tag('div',
          array(
            'class' => 'ml',
          ),
          $hovercard);
      }

      return $this->buildApplicationPage(
        $cards,
        array(
          'device' => false,
        ));
    } else {
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'cards' => $cards,
        ));
    }
  }

}
