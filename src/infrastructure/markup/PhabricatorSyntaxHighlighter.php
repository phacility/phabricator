<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group markup
 */
final class PhabricatorSyntaxHighlighter {

  public static function newEngine() {
    $engine = PhabricatorEnv::newObjectFromConfig('syntax-highlighter.engine');

    $config = array(
      'pygments.enabled' => PhabricatorEnv::getEnvConfig('pygments.enabled'),
      'filename.map'     => PhabricatorEnv::getEnvConfig('syntax.filemap'),
    );

    foreach ($config as $key => $value) {
      $engine->setConfig($key, $value);
    }

    return $engine;
  }

  public static function highlightWithFilename($filename, $source) {
    $engine = self::newEngine();
    $language = $engine->getLanguageFromFilename($filename);
    return $engine->highlightSource($language, $source);
  }

  public static function highlightWithLanguage($language, $source) {
    $engine = self::newEngine();
    return $engine->highlightSource($language, $source);
  }


}
