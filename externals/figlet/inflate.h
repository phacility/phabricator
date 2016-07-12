/*
 * inflate.h -  inflate decompression routine
 *
 * Version 1.1.2
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
 * Changes from 1.1 to 1.1.2:
 * Relicensed under the MIT license, with consent of the copyright holders.
 * Claudio Matsuoka (Jan 11 2011)
 */

/*
 * 1) All file i/o is done externally to these routines
 * 2) Routines are symmetrical so inflate can feed into deflate
 * 3) Routines can be easily integrated into wide range of applications
 * 4) Routines are very portable, and use only ANSI C
 * 5) No #defines in inflate.h to conflict with external #defines
 * 6) No external routines need be called by these routines
 * 7) Buffers are owned by the calling routine
 * 8) No static non-constant variables are allowed
 */

/*
 * Note that for each call to InflatePutBuffer, there will be
 * 0 or more calls to (*putbuffer_ptr).  All except the last
 * call to (*putbuffer_ptr) will be with 32768 bytes, although
 * this behaviour may change in the future.  Before InflatePutBuffer
 * returns, it will have output as much uncompressed data as
 * is possible.
 */

#ifndef __INFLATE_H
#define __INFLATE_H

#ifdef __cplusplus
extern "C" {
#endif

/* Routine to initialize inflate decompression */
void *InflateInitialize(                      /* returns InflateState       */
  void *AppState,                             /* for passing to putbuffer   */
  int (*putbuffer_ptr)(                       /* returns 0 on success       */
    void *AppState,                           /* opaque ptr from Initialize */
    unsigned char *buffer,                    /* buffer to put              */
    long length                               /* length of buffer           */
  ),
  void *(*malloc_ptr)(long length),           /* utility routine            */
  void (*free_ptr)(void *buffer)              /* utility routine            */
);

/* Call-in routine to put a buffer into inflate decompression */
int InflatePutBuffer(                         /* returns 0 on success       */
  void *InflateState,                         /* opaque ptr from Initialize */
  unsigned char *buffer,                      /* buffer to put              */
  long length                                 /* length of buffer           */
);

/* Routine to terminate inflate decompression */
int InflateTerminate(                         /* returns 0 on success       */
  void *InflateState                          /* opaque ptr from Initialize */
);

#ifdef __cplusplus
}
#endif

#endif
