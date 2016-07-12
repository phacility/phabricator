/*
 * crc.h - CRC calculation routine
 *
 * Version 1.0.1
 */

/*
 * Copyright (C) 1995, Edward B. Hamrick
 *
 * Permission to use, copy, modify, and distribute this software and
 * its documentation for any purpose and without fee is hereby granted,
 * provided that the above copyright notice appear in all copies and
 * that both that copyright notice and this permission notice appear in
 * supporting documentation, and that the name of the copyright holders
 * not be used in advertising or publicity pertaining to distribution of
 * the software without specific, written prior permission. The copyright
 * holders makes no representations about the suitability of this software
 * for any purpose. It is provided "as is" without express or implied warranty.
 *
 * THE COPYRIGHT HOLDERS DISCLAIM ALL WARRANTIES WITH REGARD TO THIS
 * SOFTWARE, INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS,
 * IN NO EVENT SHALL THE COPYRIGHT HOLDERS BE LIABLE FOR ANY SPECIAL, INDIRECT
 * OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF
 * USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE
 * OF THIS SOFTWARE.
 */

/*
 * Changes from 1.0 to 1.0.1:
 * Relicensed under the MIT license, with consent of the copyright holders.
 * Claudio Matsuoka (Jan 11 2011)
 */

/*
 * This CRC algorithm is the same as that used in zip.  Normally it
 * should be initialized with 0xffffffff, and the final CRC stored
 * should be crc ^ 0xffffffff.
 *
 * It implements the polynomial:
 *
 * x^32+x^26+x^23+x^22+x^16+x^12+x^11+x^10+x^8+x^7+x^5+x^4+x^2+x+1
 */

#ifndef __CRC_H
#define __CRC_H

#ifdef __cplusplus
extern "C" {
#endif

unsigned long CrcUpdate(          /* returns updated crc         */
  unsigned long crc,              /* starting crc                */
  unsigned char *buffer,          /* buffer to use to update crc */
  long length                     /* length of buffer            */
);

#ifdef __cplusplus
}
#endif

#endif
