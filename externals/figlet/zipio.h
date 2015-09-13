/*
 * zipio.h - stdio emulation library for reading zip files
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
 * Changes from 1.1 to 1.1.1:
 * Changed "z*" functions to "Z*" to avoid namespace pollution.
 * Added "zungetc" macro.
 * John Cowan <cowan@ccil.org>
 *
 * Changes from 1.1.1 to 1.1.2:
 * Relicensed under the MIT license, with consent of the copyright holders.
 * Claudio Matsuoka (Jan 11 2011)
 */

/*
 * This library of routines has the same calling sequence as
 * the stdio.h routines for reading files.  If these routines
 * detect that they are reading from a zip file, they transparently
 * unzip the file and make the application think they're reading
 * from the uncompressed file.
 *
 * Note that this library is designed to work for zip files that
 * use the deflate compression method, and to read the first file
 * within the zip archive.
 *
 * There are a number of tunable parameters in the reference
 * implementation relating to in-memory decompression and the
 * use of temporary files.
 *
 * Particular care was taken to make the Zgetc() macro work
 * as efficiently as possible.  When reading an uncompressed
 * file with Zgetc(), it has exactly the same performance as
 * when using getc().  WHen reading a compressed file with
 * Zgetc(), it has the same performance as fread().  The total
 * CPU overhead for decompression is about 50 cycles per byte.
 *
 * The Zungetc() macro is quite limited.  It ignores the character
 * specified for pushback, and essentially just forces the last
 * character read to be re-read.  This is essential when parsing
 * numbers and such.  (1.1.1)
 *
 * There are a few stdio routines that aren't represented here, but
 * they can be layered on top of these routines if needed.
 */

#ifndef __ZIPIO_H
#define __ZIPIO_H

#include <stdio.h>

typedef struct {
  int            len;
  unsigned char *ptr;
} ZFILE;

#define Zgetc(f)                   \
  ((--((f)->len) >= 0)             \
    ? (unsigned char)(*(f)->ptr++) \
    : _Zgetc (f))

#define Zungetc(c,f) \
  ((f)->ptr--, (f)->len++, (c))

#ifdef __cplusplus
extern "C" {
#endif

ZFILE  *Zopen(const char *path, const char *mode);
int    _Zgetc(ZFILE *stream);
size_t  Zread(void *ptr, size_t size, size_t n, ZFILE *stream);
int     Zseek(ZFILE *stream, long offset, int whence);
long    Ztell(ZFILE *stream);
int     Zclose(ZFILE *stream);

#ifdef __cplusplus
}
#endif

#endif
