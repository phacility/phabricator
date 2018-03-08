<?php

final class PhabricatorMacroMemeController
  extends PhabricatorMacroController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $macro_name = $request->getStr('macro');
    $upper_text = $request->getStr('uppertext');
    $lower_text = $request->getStr('lowertext');
    $viewer = $request->getViewer();

    $uri = self::generateMacro(
      $viewer,
      $macro_name,
      $upper_text,
      $lower_text);

    $content = array(
      'imageURI' => $uri,
    );

    return id(new AphrontAjaxResponse())->setContent($content);
  }

  public static function generateMacro(
    PhabricatorUser $viewer,
    $macro_name,
    $upper_text,
    $lower_text) {

    $macro = id(new PhabricatorMacroQuery())
      ->setViewer($viewer)
      ->withNames(array($macro_name))
      ->needFiles(true)
      ->executeOne();
    if (!$macro) {
      return false;
    }
    $file = $macro->getFile();

    $upper_text = phutil_utf8_strtoupper($upper_text);
    $lower_text = phutil_utf8_strtoupper($lower_text);

    $hash = PhabricatorHash::digestForIndex(
      phutil_json_encode(
        array(
          'kind' => 'meme',
          'upper' => $upper_text,
          'lower' => $lower_text,
        )));

    $xfile = self::loadTransformedFile($viewer, $file->getPHID(), $hash);
    if ($xfile) {
      return $xfile->getViewURI();
    }

    $transformer = new PhabricatorImageTransformer();

    $new_file = $transformer->executeMemeTransform(
      $file,
      $upper_text,
      $lower_text);

    $xfile = id(new PhabricatorTransformedFile())
      ->setOriginalPHID($file->getPHID())
      ->setTransformedPHID($new_file->getPHID())
      ->setTransform($hash);

    try {
      $caught = null;

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      try {
        $xfile->save();
      } catch (Exception $ex) {
        $caught = $ex;
      }
      unset($unguarded);

      if ($caught) {
        throw $caught;
      }

      return $new_file->getViewURI();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      $xfile = self::loadTransformedFile($viewer, $file->getPHID(), $hash);
      if (!$xfile) {
        throw $ex;
      }
      return $xfile->getViewURI();
    }
  }

  private static function loadTransformedFile(
    PhabricatorUser $viewer,
    $file_phid,
    $hash) {

    $xform = id(new PhabricatorTransformedFile())->loadOneWhere(
      'originalPHID = %s AND transform = %s',
      $file_phid,
      $hash);
    if (!$xform) {
      return null;
    }

    return id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xform->getTransformedPHID()))
      ->executeOne();
  }
}
