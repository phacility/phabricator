# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

FROM alpine:3.3

RUN apk --no-cache add tinyproxy

COPY tinyproxy.conf /tinyproxy.conf

EXPOSE 1090
ENTRYPOINT ["tinyproxy"]
CMD ["-d", "-c", "/tinyproxy.conf"]
