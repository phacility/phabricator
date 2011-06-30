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

  // If you have multiple environments, provide the production environment URI
  // here so that emails, etc., generated in development/sandbox environments
  // contain the right links.
  'phabricator.production-uri'  => null,

  // Setting this to 'true' will invoke a special setup mode which helps guide
  // you through setting up Phabricator.
  'phabricator.setup'           => false,

  // The default PHID for users who haven't uploaded a profile image. It should
  // be 50x50px.
  'user.default-profile-image-phid' => 'PHID-FILE-4d61229816cfe6f2b2a3',

// -- DarkConsole ----------------------------------------------------------- //

  // DarkConsole is a administrative debugging/profiling tool built into
  // Phabricator. You can leave it disabled unless you're developing against
  // Phabricator.

  // Determines whether or not DarkConsole is available. DarkConsole exposes
  // some data like queries and stack traces, so you should be careful about
  // turning it on in production (although users can not normally see it, even
  // if the deployment configuration enables it).
  'darkconsole.enabled'         => false,

  // Always enable DarkConsole, even for logged out users. This potentially
  // exposes sensitive information to users, so make sure untrusted users can
  // not access an install running in this mode. You should definitely leave
  // this off in production. It is only really useful for using DarkConsole
  // utilties to debug or profile logged-out pages. You must set
  // 'darkconsole.enabled' to use this option.
  'darkconsole.always-on'       => false,


  // Allows you to mask certain configuration values from appearing in the
  // "Config" tab of DarkConsole.
  'darkconsole.config-mask'     => array(
    'mysql.pass',
    'amazon-ses.secret-key',
    'recaptcha.private-key',
    'phabricator.csrf-key',
    'facebook.application-secret',
    'github.application-secret',
  ),

// --  MySQL  --------------------------------------------------------------- //

  // The username to use when connecting to MySQL.
  'mysql.user' => 'root',

  // The password to use when connecting to MySQL.
  'mysql.pass' => '',

  // The MySQL server to connect to. If you want to connect to a different
  // port than the default (which is 3306), specify it in the hostname
  // (e.g., db.example.com:1234).
  'mysql.host' => 'localhost',

// -- Email ----------------------------------------------------------------- //

  // Some Phabricator tools send email notifications, e.g. when Differential
  // revisions are updated or Maniphest tasks are changed. These options allow
  // you to configure how email is delivered.

  // You can test your mail setup by going to "MetaMTA" in the web interface,
  // clicking "Send New Message", and then composing a message.

  // Default address to send mail "From".
  'metamta.default-address'     => 'noreply@example.com',

  // Domain used to generate Message-IDs.
  'metamta.domain'              => 'example.com',

  // When a user takes an action which generates an email notification (like
  // commenting on a Differential revision), Phabricator can either send that
  // mail "From" the user's email address (like "alincoln@logcabin.com") or
  // "From" the 'metamta.default-address' address. The user experience is
  // generally better if Phabricator uses the user's real address as the "From"
  // since the messages are easier to organize when they appear in mail clients,
  // but this will only work if the server is authorized to send email on behalf
  // of the "From" domain. Practically, this means:
  //    - If you are doing an install for Example Corp and all the users will
  //      have corporate @corp.example.com addresses and any hosts Phabricator
  //      is running on are authorized to send email from corp.example.com,
  //      you can enable this to make the user experience a little better.
  //    - If you are doing an install for an open source project and your
  //      users will be registering via Facebook and using personal email
  //      addresses, you MUST NOT enable this or virtually all of your outgoing
  //      email will vanish into SFP blackholes.
  //    - If your install is anything else, you're much safer leaving this
  //      off since the risk in turning it on is that your outgoing mail will
  //      mostly never arrive.
  'metamta.can-send-as-user'    => false,

  // Adapter class to use to transmit mail to the MTA. The default uses
  // PHPMailerLite, which will invoke "sendmail". This is appropriate
  // if sendmail actually works on your host, but if you haven't configured mail
  // it may not be so great. You can also use Amazon SES, by changing this to
  // 'PhabricatorMailImplementationAmazonSESAdapter', signing up for SES, and
  // filling in your 'amazon-ses.access-key' and 'amazon-ses.secret-key' below.
  'metamta.mail-adapter'        =>
    'PhabricatorMailImplementationPHPMailerLiteAdapter',

  // When email is sent, try to hand it off to the MTA immediately. This may
  // be worth disabling if your MTA infrastructure is slow or unreliable. If you
  // disable this option, you must run the 'metamta_mta.php' daemon or mail
  // won't be handed off to the MTA. If you're using Amazon SES it can be a
  // little slugish sometimes so it may be worth disabling this and moving to
  // the daemon after you've got your install up and running. If you have a
  // properly configured local MTA it should not be necessary to disable this.
  'metamta.send-immediately'    => true,

  // If you're using Amazon SES to send email, provide your AWS access key
  // and AWS secret key here. To set up Amazon SES with Phabricator, you need
  // to:
  //  - Make sure 'metamta.mail-adapter' is set to:
  //    "PhabricatorMailImplementationAmazonSESAdapter"
  //  - Make sure 'metamta.can-send-as-user' is false.
  //  - Make sure 'metamta.default-address' is configured to something sensible.
  //  - Make sure 'metamta.default-address' is a validated SES "From" address.
  'amazon-ses.access-key'       =>  null,
  'amazon-ses.secret-key'       =>  null,

  // If you're using Sendgrid to send email, provide your access credentials
  // here. This will use the REST API. You can also use Sendgrid as a normal
  // SMTP service.
  'sendgrid.api-user'           => null,
  'sendgrid.api-key'            => null,

  // You can configure a reply handler domain so that email sent from Maniphest
  // will have a special "Reply To" address like "T123+82+af19f@example.com"
  // that allows recipients to reply by email and interact with tasks. For
  // instructions on configurating reply handlers, see the article
  // "Configuring Inbound Email" in the Phabricator documentation. By default,
  // this is set to 'null' and Phabricator will use a generic 'noreply@' address
  // or the address of the acting user instead of a special reply handler
  // address (see 'metamta.default-address'). If you set a domain here,
  // Phabricator will begin generating private reply handler addresses. See
  // also 'metamta.maniphest.reply-handler' to further configure behavior.
  // This key should be set to the domain part after the @, like "example.com".
  'metamta.maniphest.reply-handler-domain' => null,

  // You can follow the instructions in "Configuring Inbound Email" in the
  // Phabricator documentation and set 'metamta.maniphest.reply-handler-domain'
  // to support updating Maniphest tasks by email. If you want more advanced
  // customization than this provides, you can override the reply handler
  // class with an implementation of your own. This will allow you to do things
  // like have a single public reply handler or change how private reply
  // handlers are generated and validated.
  // This key should be set to a loadable subclass of
  // PhabricatorMailReplyHandler (and possibly of ManiphestReplyHandler).
  'metamta.maniphest.reply-handler' => 'ManiphestReplyHandler',

  // Prefix prepended to mail sent by Maniphest. You can change this to
  // distinguish between testing and development installs, for example.
  'metamta.maniphest.subject-prefix' => '[Maniphest]',

  // See 'metamta.maniphest.reply-handler-domain'. This does the same thing,
  // but allows email replies via Differential.
  'metamta.differential.reply-handler-domain' => null,

  // See 'metamta.maniphest.reply-handler'. This does the same thing, but
  // affects Differential.
  'metamta.differential.reply-handler' => 'DifferentialReplyHandler',

  // Prefix prepended to mail sent by Differential.
  'metamta.differential.subject-prefix' => '[Differential]',

  // By default, Phabricator generates unique reply-to addresses and sends a
  // separate email to each recipient when you enable reply handling. This is
  // more secure than using "From" to establish user identity, but can mean
  // users may receive multiple emails when they are on mailing lists. Instead,
  // you can use a single, non-unique reply to address and authenticate users
  // based on the "From" address by setting this to 'true'. This trades away
  // a little bit of security for convenience, but it's reasonable in many
  // installs. Object interactions are still protected using hashes in the
  // single public email address, so objects can not be replied to blindly.
  'metamta.public-replies' => false,


// -- Auth ------------------------------------------------------------------ //

  // Can users login with a username/password, or by following the link from
  // a password reset email? You can disable this and configure one or more
  // OAuth providers instead.
  'auth.password-auth-enabled'  => true,

  // Maximum number of simultaneous web sessions each user is permitted to have.
  // Setting this to "1" will prevent a user from logging in on more than one
  // browser at the same time.
  'auth.sessions.web'           => 5,

  // Maximum number of simultaneous Conduit sessions each user is permitted
  // to have.
  'auth.sessions.conduit'       => 3,


// -- Accounts -------------------------------------------------------------- //

  // Is basic account information (email, real name, profile picture) editable?
  // If you set up Phabricator to automatically synchronize account information
  // from some other authoritative system, you can disable this to ensure
  // information remains consistent across both systems.
  'account.editable'            => true,


// --  Facebook  ------------------------------------------------------------ //

  // Can users use Facebook credentials to login to Phabricator?
  'facebook.auth-enabled'       => false,

  // Can users use Facebook credentials to create new Phabricator accounts?
  'facebook.registration-enabled' => true,

  // Are Facebook accounts permanently linked to Phabricator accounts, or can
  // the user unlink them?
  'facebook.auth-permanent'     => false,

  // The Facebook "Application ID" to use for Facebook API access.
  'facebook.application-id'     => null,

  // The Facebook "Application Secret" to use for Facebook API access.
  'facebook.application-secret' => null,


// -- Github ---------------------------------------------------------------- //

  // Can users use Github credentials to login to Phabricator?
  'github.auth-enabled'         => false,

  // Can users use Github credentials to create new Phabricator accounts?
  'github.registration-enabled' => true,

  // Are Github accounts permanently linked to Phabricator accounts, or can
  // the user unlink them?
  'github.auth-permanent'       => false,

  // The Github "Client ID" to use for Github API access.
  'github.application-id'       => null,

  // The Github "Secret" to use for Github API access.
  'github.application-secret'   => null,


// -- Recaptcha ------------------------------------------------------------- //

  // Is Recaptcha enabled? If disabled, captchas will not appear.
  'recaptcha.enabled'           => false,

  // Your Recaptcha public key, obtained from Recaptcha.
  'recaptcha.public-key'        => null,

  // Your Recaptcha private key, obtained from Recaptcha.
  'recaptcha.private-key'       => null,


// -- Misc ------------------------------------------------------------------ //

  // This is hashed with other inputs to generate CSRF tokens. If you want, you
  // can change it to some other string which is unique to your install. This
  // will make your install more secure in a vague, mostly theoretical way. But
  // it will take you like 3 seconds of mashing on your keyboard to set it up so
  // you might as well.
  'phabricator.csrf-key'        => '0b7ec0592e0a2829d8b71df2fa269b2c6172eca3',

  // This is hashed with other inputs to generate mail tokens. If you want, you
  // can change it to some other string which is unique to your install. In
  // particular, you will want to do this if you accidentally send a bunch of
  // mail somewhere you shouldn't have, to invalidate all old reply-to
  // addresses.
  'phabricator.mail-key'        => '5ce3e7e8787f6e40dfae861da315a5cdf1018f12',

  // Version string displayed in the footer. You probably should leave this
  // alone.
  'phabricator.version'         => 'UNSTABLE',

  // PHP requires that you set a timezone in your php.ini before using date
  // functions, or it will emit a warning. If this isn't possible (for instance,
  // because you are using HPHP) you can set some valid constant for
  // date_default_timezone_set() here and Phabricator will set it on your
  // behalf, silencing the warning.
  'phabricator.timezone'        => null,


  // Phabricator can highlight PHP by default, but if you want syntax
  // highlighting for other languages you should install the python package
  // 'Pygments', make sure the 'pygmentize' script is available in the
  // $PATH of the webserver, and then enable this.
  'pygments.enabled'            => false,


// -- Files ----------------------------------------------------------------- //

  // Lists which uploaded file types may be viewed in the browser. If a file
  // has a mime type which does not appear in this list, it will always be
  // downloaded instead of displayed. This is a security consideration: if a
  // user uploads a file of type "text/html" and it is displayed as
  // "text/html", they can easily execute XSS attacks. This is also a usability
  // consideration, since browsers tend to freak out when viewing enormous
  // binary files.
  //
  // The keys in this array are viewable mime types; the values are the mime
  // types they will be delivered as when they are viewed in the browser.
  'files.viewable-mime-types' => array(
    'image/jpeg'  => 'image/jpeg',
    'image/jpg'   => 'image/jpg',
    'image/png'   => 'image/png',
    'image/gif'   => 'image/gif',
    'text/plain'  => 'text/plain; charset=utf-8',
  ),

  // Phabricator can proxy images from other servers so you can paste the URI
  // to a funny picture of a cat into the comment box and have it show up as an
  // image. However, this means the webserver Phabricator is running on will
  // make HTTP requests to arbitrary URIs. If the server has access to internal
  // resources, this could be a security risk. You should only enable it if you
  // are installed entirely a VPN and VPN access is required to access
  // Phabricator, or if the webserver has no special access to anything. If
  // unsure, it is safer to leave this disabled.
  'files.enable-proxy' => false,

// -- Differential ---------------------------------------------------------- //

  'differential.revision-custom-detail-renderer'  => null,

  // Array for custom remarkup rules. The array should have a list of
  // class names of classes that extend PhutilRemarkupRule
  'differential.custom-remarkup-rules' => null,

  // Array for custom remarkup block rules. The array should have a list of
  // class names of classes that extend PhutilRemarkupEngineBlockRule
  'differential.custom-remarkup-block-rules' => null,

  // Set display word-wrap widths for Differential. Specify a dictionary of
  // regular expressions mapping to column widths. The filename will be matched
  // against each regexp in order until one matches. The default configuration
  // uses a width of 100 for Java and 80 for other languages. Note that 80 is
  // the greatest column width of all time. Changes here will not be immediately
  // reflected in old revisions unless you purge the render cache.
  'differential.wordwrap' => array(
    '/\.java$/' => 100,
    '/.*/'      => 80,
  ),

  // Class for appending custom fields to be included in the commit
  // messages generated by "arc amend".  Should inherit
  // DifferentialCommitMessageModifier
  'differential.modify-commit-message-class' => null,


// -- Maniphest ------------------------------------------------------------- //

  'maniphest.enabled' => true,

// -- Remarkup -------------------------------------------------------------- //

  // If you enable this, linked YouTube videos will be embeded inline. This has
  // mild security implications (you'll leak referrers to YouTube) and is pretty
  // silly (but sort of awesome).
  'remarkup.enable-embedded-youtube' => false,

// -- Customization --------------------------------------------------------- //

  // Paths to additional phutil libraries to load.
  'load-libraries' => array(),

  'aphront.default-application-configuration-class' =>
    'AphrontDefaultApplicationConfiguration',

  'controller.oauth-registration' =>
    'PhabricatorOAuthDefaultRegistrationController',


  // Directory that phd (the Phabricator daemon control script) should use to
  // track running daemons.
  'phd.pid-directory' => '/var/tmp/phd',

  // This value is an input to the hash function when building resource hashes.
  // It has no security value, but if you accidentally poison user caches (by
  // pushing a bad patch or having something go wrong with a CDN, e.g.) you can
  // change this to something else and rebuild the Celerity map to break user
  // caches. Unless you are doing Celerity development, it is exceptionally
  // unlikely that you need to modify this.
  'celerity.resource-hash' => 'd9455ea150622ee044f7931dabfa52aa',

  // In a development environment, it is desirable to force static resources
  // (CSS and JS) to be read from disk on every request, so that edits to them
  // appear when you reload the page even if you haven't updated the resource
  // maps. This setting ensures requests will be verified against the state on
  // disk. Generally, you should leave this off in production (caching behavior
  // and performance improve with it off) but turn it on in development. (These
  // settings are the defaults.)
  'celerity.force-disk-reads' => false,

);
