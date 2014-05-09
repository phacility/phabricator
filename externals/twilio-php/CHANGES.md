twilio-php Changelog
====================

Version 3.12.4
--------------

Released on January 30, 2014

- Fix incorrect use of static:: which broke compatibility with PHP 5.2.

Version 3.12.3
--------------

Released on January 28, 2014

- Add link from recordings to associated transcriptions.
- Document how to debug requests, improve TwiML generation docs.

Version 3.12.2
--------------

Released on January 5, 2014

- Fixes string representation of resources
- Support PHP 5.5

Version 3.12.1
--------------

Released on October 21, 2013

- Add support for filtering by type for IncomingPhoneNumbers.
- Add support for searching for mobile numbers for both
IncomingPhoneNumbers and AvailablePhoneNumbers.

Version 3.12.0
--------------

Released on September 18, 2013

- Support MMS
- Support SIP In
- $params arrays will now turn lists into multiple HTTP keys with the same name,

        array("Twilio" => array('foo', 'bar'))

    will turn into Twilio=foo&Twilio=bar when sent to the API.

- Update the documentation to use php-autodoc and Sphinx.

Version 3.11.0
--------------

Released on June 13

- Support Streams when curl is not available for PHP installations

Version 3.10.0
--------------

Released on February 2, 2013

- Uses the [HTTP status code for error reporting][http], instead of the
`status` attribute of the JSON response. (Reporter: [Ruud Kamphuis](/ruudk))

[http]: https://github.com/twilio/twilio-php/pull/116

Version 3.9.1
-------------

Released on December 30, 2012

- Adds a `$last_response` parameter to the `$client` object that can be
used to [retrieve the raw API response][last-response]. (Reporter: [David
Jones](/dxjones))

[last-response]: https://github.com/twilio/twilio-php/pull/112/files

Version 3.9.0
-------------

Released on December 20, 2012

- [Fixes TwiML generation to handle non-ASCII characters properly][utf-8]. Note
  that as of version 3.9.0, **the library requires PHP version 5.2.3, at least
  for TwiML generation**. (Reporter: [Walker Hamilton](/walker))

[utf-8]: https://github.com/twilio/twilio-php/pull/111

Version 3.8.3
-------------

Released on December 15, 2012

- [Fixes the ShortCode resource][shortcode] so it is queryable via the PHP library.

 [shortcode]: https://github.com/twilio/twilio-php/pull/108

Version 3.8.2
-------------

Released on November 26, 2012

- Fixes an issue where you [could not iterate over the members in a
queue][queue-members]. (Reporter: [Alex Chan](/alexcchan))

[queue-members]: https://github.com/twilio/twilio-php/pull/107

Version 3.8.1
-------------

Released on November 23, 2012

- [Implements the Countable interface on the ListResource][countable], so you
  can call count() on any resource.
- [Adds a convenience method for retrieving a phone number object][get-number],
  so you can retrieve all of a number's properties by its E.164 representation.

Internally:

- Adds [unit tests for url encoding of Unicode characters][unicode-tests].
- Updates [Travis CI configuration to use Composer][travis-composer],
shortening build time from 83 seconds to 21 seconds.

[countable]: https://twilio-php.readthedocs.org/en/latest/usage/rest.html#retrieving-the-total-number-of-resources
[get-number]: https://twilio-php.readthedocs.org/en/latest/usage/rest/phonenumbers.html#retrieving-all-of-a-number-s-properties
[unicode-tests]: https://github.com/twilio/twilio-php/commit/6f8aa57885796691858e460c8cea748e241c47e3
[travis-composer]: https://github.com/twilio/twilio-php/commit/a732358e90e1ae9a5a3348ad77dda8cc8b5ec6bc

Version 3.8.0
-------------

Released on October 17, 2012

- Support the new Usage API, with Usage Records and Usage Triggers. Read the
  PHP documentation for [usage records][records] or [usage triggers][triggers]

  [records]: https://twilio-php.readthedocs.org/en/latest/usage/rest/usage-records.html
  [triggers]: https://twilio-php.readthedocs.org/en/latest/usage/rest/usage-triggers.html

Version 3.7.2
-------------

- The library will now [use a standard CA cert whitelist][whitelist] for SSL
    validation, replacing a file that contained only Twilio's SSL certificate.
    (Reporter: [Andrew Benton](/andrewmbenton))

 [whitelist]: https://github.com/twilio/twilio-php/issues/88

Version 3.7.1
-------------

Released on August 16, 2012

- Fix a bug in the 3.5.0 release where [updating an instance
  resource would cause subsequent updates to request an incorrect
  URI](/twilio/twilio-php/pull/82).
  (Reporter: [Dan Bowen](/crucialwebstudio))

Version 3.7.0
-------------

Released on August 6, 2012

- Add retry support for idempotent HTTP requests that result in a 500 server
  error (default is 1 attempt, however this can be configured).
- Throw a Services_Twilio_RestException instead of a DomainException if the
  response content cannot be parsed as JSON (usually indicates a 500 error)

Version 3.6.0
-------------

Released on August 5, 2012

- Add support for Queues and Members. Includes tests and documentation for the
  new functionality.

Version 3.5.2
-------------

Released on July 23, 2012

- Fix an issue introduced in the 3.5.0 release where updating or muting
  a participant would [throw an exception instead of muting the
  participant][mute-request].
  (Reporter: [Alex Chan](/alexcchan))

- Fix an issue introduced in the 3.5.0 release where [filtering an iterator
with parameters would not work properly][paging-request] on subsequent HTTP
requests. (Reporters: [Alex Chan](/alexcchan), Ivor O'Connor)

[mute-request]: /twilio/twilio-php/pull/74
[paging-request]: /twilio/twilio-php/pull/75

Version 3.5.1
-------------

Released on July 2, 2012

- Fix an issue introduced in the 3.5.0 release that would cause a second HTTP
request for an instance resource [to request an incorrect URI][issue-71].

[issue-71]: https://github.com/twilio/twilio-php/pull/71

Version 3.5.0
-------------

Released on June 30, 2012

- Support paging through resources using the `next_page_uri` parameter instead
of manually constructing parameters using the `Page` and `PageSize` parameters.
Specifically, this allows the library to use the `AfterSid` parameter, which
leads to improved performance when paging deep into your resource list.

This involved a major refactor of the library. The documented interface to
twilio-php will not change. However, some undocumented public methods are no
longer supported. Specifically, the following classes are no longer available:

- `Services/Twilio/ArrayDataProxy.php`
- `Services/Twilio/CachingDataProxy.php`
- `Services/Twilio/DataProxy.php`

In addition, the following public methods have been removed:

- `setProxy`, in `Services/Twilio/InstanceResource.php`
- `getSchema`, in `Services/Twilio/ListResource.php`,
    `Services/Twilio/Rest/AvailablePhoneNumbers.php`,
    `Services/Twilio/Rest/SMSMessages.php`

- `retrieveData`, in `Services/Twilio/Resource.php`
- `deleteData`, in `Services/Twilio/Resource.php`
- `addSubresource`, in `Services/Twilio/Resource.php`

Please check your own code for compatibility before upgrading.

Version 3.3.2
-------------

Released on May 3, 2012

- If you pass booleans in as TwiML (ex transcribe="true"), convert them to
  the strings "true" and "false" instead of outputting the incorrect values
  1 and "".

Version 3.3.1
-------------

Released on May 1, 2012

- Use the 'Accept-Charset' header to specify we want to receive UTF-8 encoded
data from the Twilio API. Remove unused XML parsing logic, as the library never
requests XML data.

Version 3.2.4
-------------

Released on March 14, 2012

- If no version is passed to the Services_Twilio constructor, the library will
  default to the most recent API version.

