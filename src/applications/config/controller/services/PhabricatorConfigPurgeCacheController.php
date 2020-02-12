<?php

final class PhabricatorConfigPurgeCacheController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $cancel_uri = $this->getApplicationURI('cache/');

    $opcode_cache = PhabricatorOpcodeCacheSpec::getActiveCacheSpec();
    $data_cache = PhabricatorDataCacheSpec::getActiveCacheSpec();

    $opcode_clearable = $opcode_cache->getClearCacheCallback();
    $data_clearable = $data_cache->getClearCacheCallback();

    if (!$opcode_clearable && !$data_clearable) {
      return $this->newDialog()
        ->setTitle(pht('No Caches to Reset'))
        ->appendParagraph(
          pht('None of the caches on this page can be cleared.'))
        ->addCancelButton($cancel_uri);
    }

    if ($request->isDialogFormPost()) {
      if ($opcode_clearable) {
        call_user_func($opcode_cache->getClearCacheCallback());
      }

      if ($data_clearable) {
        call_user_func($data_cache->getClearCacheCallback());
      }

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $caches = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if ($opcode_clearable) {
      $caches->addProperty(
        pht('Opcode'),
        $opcode_cache->getName());
    }

    if ($data_clearable) {
      $caches->addProperty(
        pht('Data'),
        $data_cache->getName());
    }

    return $this->newDialog()
      ->setTitle(pht('Really Clear Cache?'))
      ->setShortTitle(pht('Really Clear Cache'))
      ->appendParagraph(pht('This will only affect the current web '.
      'frontend. Daemons and any other web frontends may continue '.
      'to use older, cached code from their opcache.'))
      ->appendParagraph(pht('The following caches will be cleared:'))
      ->appendChild($caches)
      ->addSubmitButton(pht('Clear Cache'))
      ->addCancelButton($cancel_uri);
  }
}
