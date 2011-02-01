<?php

/*
 * Copyright 2011 Facebook, Inc.
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

return array(

  // The root URI which Phabricator is installed on.
  // Example: "http://phabricator.example.com/"
  'phabricator.base-uri'        => null,


  //
  'phabricator.csrf-key'        => '0b7ec0592e0a2829d8b71df2fa269b2c6172eca3',


// --  Facebook  ---------------------------------------------------------------

  // Can users use Facebook credentials to login to Phabricator?
  'facebook.auth-enabled'       => false,

  // The Facebook "Application ID" to use for Facebook API access.
  'facebook.application-id'     => null,

  // The Facebook "Application Secret" to use for Facebook API access.
  'facebook.application-secret' => null,

  'recaptcha.public-key'        => null,
  'recaptcha.private-key'       => null,


  'user.default-profile-image-phid' => 'PHID-FILE-f57aaefce707fc4060ef',


);
