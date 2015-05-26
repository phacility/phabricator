<?php

final class PhabricatorMacroMemeController
  extends PhabricatorMacroController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $macro_name = $request->getStr('macro');
    $upper_text = $request->getStr('uppertext');
    $lower_text = $request->getStr('lowertext');
    $user = $request->getUser();

    $uri = self::generateMacro($user, $macro_name,
      $upper_text, $lower_text);
    if ($uri === false) {
      return new Aphront404Response();
    }
    return id(new AphrontRedirectResponse())
      ->setIsExternal(true)
      ->setURI($uri);
  }

  public static function generateMacro($user, $macro_name, $upper_text,
      $lower_text) {
    $macro = id(new PhabricatorMacroQuery())
      ->setViewer($user)
      ->withNames(array($macro_name))
      ->needFiles(true)
      ->executeOne();
    if (!$macro) {
      return false;
    }
    $file = $macro->getFile();

    $upper_text = strtoupper($upper_text);
    $lower_text = strtoupper($lower_text);
    $mixed_text = md5($upper_text).':'.md5($lower_text);
    $hash = 'meme'.hash('sha256', $mixed_text);
    $xform = id(new PhabricatorTransformedFile())
      ->loadOneWhere('originalphid=%s and transform=%s',
        $file->getPHID(), $hash);

    if ($xform) {
      $memefile = id(new PhabricatorFileQuery())
        ->setViewer($user)
        ->withPHIDs(array($xform->getTransformedPHID()))
        ->executeOne();
      if ($memefile) {
        return $memefile->getBestURI();
      }
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $transformers = (new PhabricatorImageTransformer());
    $newfile = $transformers
      ->executeMemeTransform($file, $upper_text, $lower_text);
    $xfile = new PhabricatorTransformedFile();
    $xfile->setOriginalPHID($file->getPHID());
    $xfile->setTransformedPHID($newfile->getPHID());
    $xfile->setTransform($hash);
    $xfile->save();

    return $newfile->getBestURI();
  }
}
