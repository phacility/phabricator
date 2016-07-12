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

    $cards = array();
    foreach ($phids as $phid) {
      $handle = $handles[$phid];
      $object = idx($objects, $phid);

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

      $cards[$phid] = $hovercard;
    }

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'cards' => $cards,
        ));
    }

    foreach ($cards as $key => $hovercard) {
      $cards[$key] = phutil_tag('div',
        array(
          'class' => 'ml',
        ),
        $hovercard);
    }

    return $this->newPage()
      ->appendChild($cards)
      ->setShowFooter(false);
  }

}
