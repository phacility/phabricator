/*
 * zipio.c - stdio emulation library for reading zip files
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
 * Added definitions of SEEK_SET, SEEK_CUR, SEEK_END for the Posixly challenged
 * John Cowan <cowan@ccil.org>
 *
 * Changes from 1.1.1 to 1.1.2:
 * Relicensed under the MIT license, with consent of the copyright holders.
 * Avoid usage of unitialized "length" variable in _Zgetc
 * Claudio Matsuoka (Jan 11 2011)
 */

/*
 * Refer to zipio.h for a description of this package.
 */

/*
 * The .zip file header is described below.  It consists of
 * 30 fixed bytes, followed by two variable length fields
 * whose length is contained in the first 30 bytes.  After this
 * header, the data is stored (in deflate format if the compression
 * method is 8).
 *
 * The crc-32 field is the crc on the uncompressed data.
 *
 * .zip file header:
 *
 *      local file header signature     4 bytes  (0x04034b50)
 *      version needed to extract       2 bytes
 *      general purpose bit flag        2 bytes
 *      compression method              2 bytes
 *      last mod file time              2 bytes
 *      last mod file date              2 bytes
 *      crc-32                          4 bytes
 *      compressed size                 4 bytes
 *      uncompressed size               4 bytes
 *      filename length                 2 bytes
 *      extra field length              2 bytes
 *
 *      filename (variable size)
 *      extra field (variable size)
 *
 * These fields are described in more detail in appnote.txt
 * in the pkzip 1.93 distribution.
 */

#include <stdlib.h>
#ifdef MEMCPY
#include <mem.h>
#endif

#include "zipio.h"
#include "inflate.h"
#include "crc.h"

/*
 * Macros for constants
 */

#ifndef NULL
#define NULL ((void *) 0)
#endif

#ifndef TRUE
#define TRUE 1
#endif

#ifndef FALSE
#define FALSE 0
#endif

#ifndef ZIPSIGNATURE
#define ZIPSIGNATURE     0x04034b50L
#endif

#ifndef SEEK_SET
#define SEEK_SET	0
#endif

#ifndef SEEK_CUR
#define SEEK_CUR	1
#endif

#ifndef SEEK_END
#define SEEK_END	2
#endif


/* 
 * Buffer size macros
 *
 * The following constants are optimized for large-model
 * (but not flat model) Windows with virtual memory.  It 
 * will work fine on unix and flat model Windows as well.  
 *
 * The constant BUFFERTHRESHOLD determines when memory
 * buffering changes to file buffering.
 *
 * Assumptions:
 *
 *   1) INPBUFSIZE + OUTBUFSIZE + sizeof(void *) * PTRBUFSIZE + delta < 64K
 *
 *   2) OUTBUFSIZE = 32K * N (related to inflate's 32K window size)
 *
 *   2) Max in-memory file size is OUTBUFSIZE * PTRBUFSIZE
 *      which is 64 MBytes by default (32K * 2K).
 *
 */

#ifndef BUFFERTHRESHOLD
#define BUFFERTHRESHOLD            (256 * 1024L)
#endif

#ifndef INPBUFSIZE
#define INPBUFSIZE                 (  8 * 1024 )
#endif

#ifndef PTRBUFSIZE
#define PTRBUFSIZE                 (  2 * 1024 )
#endif

#ifndef OUTBUFSIZE
#define OUTBUFSIZE ((unsigned int) ( 32 * 1024L))
#endif

#define MAXFILESIZE (OUTBUFSIZE * (long) PTRBUFSIZE)

/*
 * Macro for short-hand reference to ZipioState (from ZFILE *)
 */

#define ZS ((struct ZipioState *) stream)

/*
 * Macro to manipulate Zgetc() cache
 */

#define CACHEINIT                                 \
  zs->ptr = NULL;                                 \
  zs->len = 0;

#define CACHEUPDATE                               \
  if (ZS->ptr)                                    \
  {                                               \
    ZS->fileposition &= ~((long) (OUTBUFSIZE-1)); \
    ZS->fileposition += ZS->ptr - ZS->getbuf;     \
    ZS->ptr = NULL;                               \
  }                                               \
  ZS->len = 0;

/*
 * Macros for run-time type identification
 */

#ifndef RUNTIMEENABLE
#define RUNTIMEENABLE 0
#endif

#if RUNTIMEENABLE
#define ZIPIOSTATETYPE   0x0110f00fL
#define RUNTIMEINIT                                             \
  zs->runtimetypeid1   = ZIPIOSTATETYPE;                        \
  zs->runtimetypeid2   = ZIPIOSTATETYPE;

#define RUNTIMECHECK                                            \
  if (!ZS || (ZS->runtimetypeid1 != ZIPIOSTATETYPE)             \
          || (ZS->runtimetypeid2 != ZIPIOSTATETYPE)) return -1;
#else
#define RUNTIMEINIT
#define RUNTIMECHECK
#endif

/*
 * Macros for converting bytes to unsigned integers
 */

#define GETUINT4(ptr, i4)                                               \
  {                                                                     \
    i4 = (((unsigned long) *(((unsigned char *) (ptr)) + 0))      ) |   \
         (((unsigned long) *(((unsigned char *) (ptr)) + 1)) <<  8) |   \
         (((unsigned long) *(((unsigned char *) (ptr)) + 2)) << 16) |   \
         (((unsigned long) *(((unsigned char *) (ptr)) + 3)) << 24)   ; \
  }

#define GETUINT2(ptr, i2)                                               \
  {                                                                     \
    i2 = (((unsigned  int) *(((unsigned char *) (ptr)) + 0))      ) |   \
         (((unsigned  int) *(((unsigned char *) (ptr)) + 1)) <<  8)   ; \
  }

/* Structure to hold state for decoding zip files */
struct ZipioState {

  /* Fields overlaid with ZFILE structure */
  int            len;                        /* length of Zgetc cache      */
  unsigned char *ptr;                        /* pointer to Zgetc cache     */

  /* Fields invisible to users of ZFILE structure */

  unsigned long  runtimetypeid1;             /* to detect run-time errors  */
  int            errorencountered;           /* error encountered flag     */

  /* Buffering state */
  unsigned char  inpbuf[INPBUFSIZE];         /* inp buffer from zip file   */
  unsigned char *ptrbuf[PTRBUFSIZE];         /* pointers to in-memory bufs */

  unsigned char  getbuf[OUTBUFSIZE];         /* buffer for use by Zgetc    */
  long           getoff;                     /* starting offset of getbuf  */

  FILE          *tmpfil;                     /* file ptr to temp file      */

  /* Amount of input data inflated */
  unsigned long  inpinf;
  unsigned long  outinf;

  /* Zip file header */
  unsigned long  sign;     /* local file header signature (0x04034b50) */
  unsigned int   vers;     /* version needed to extract       2 bytes  */
  unsigned int   flag;     /* general purpose bit flag        2 bytes  */
  unsigned int   comp;     /* compression method              2 bytes  */
  unsigned int   mtim;     /* last mod file time              2 bytes  */
  unsigned int   mdat;     /* last mod file date              2 bytes  */
  unsigned long  crc3;     /* crc-32                          4 bytes  */
  unsigned long  csiz;     /* compressed size                 4 bytes  */
  unsigned long  usiz;     /* uncompressed size               4 bytes  */
  unsigned int   flen;     /* filename length                 2 bytes  */
  unsigned int   elen;     /* extra field length              2 bytes  */

  /* Application state */
  FILE          *OpenFile;                   /* currently open file        */

  void          *inflatestate;               /* current state for inflate  */

  unsigned long  fileposition;               /* current file position      */

  unsigned long  filecrc;                    /* current crc                */

  unsigned long  runtimetypeid2;             /* to detect run-time errors  */
};

/*
 * Utility routines to handle uncompressed file buffers
 */

/* Initialize buffering */
static void BufferInitialize(
  struct ZipioState *zs,
  int doinflate
)
{
  zs->getoff = -1;
  zs->tmpfil = NULL;

  /*
   * If not inflating, use the input file
   */

  if (!doinflate)
  {
    zs->tmpfil = zs->OpenFile;

    /* Get the uncompressed file size */
    fseek(zs->tmpfil, 0, SEEK_END);
    zs->usiz = ftell(zs->tmpfil);
    zs->outinf = zs->usiz;

    /* Start at the beginning */
    fseek(zs->tmpfil, 0, SEEK_SET);
  }

  /* If there's no file open, see if it's big enough for temp file */
  if (!zs->tmpfil)
  {
    if (zs->usiz >= BUFFERTHRESHOLD)
      zs->tmpfil = tmpfile();
  }

  /* If there's no file open, then use memory buffering */
  if (!zs->tmpfil)
  {
    int i;

    for (i=0; i<PTRBUFSIZE; i++)
      zs->ptrbuf[i] = NULL;
  }
}

/* pump data till length bytes of file are inflated or error encountered */
static int BufferPump(struct ZipioState *zs, long length)
{
  size_t inplen, ret;

  /* Check to see if the length is valid */
  if (length > zs->usiz) return TRUE;

  /* Loop till enough data is pumped */
  while (!zs->errorencountered && (zs->outinf < length))
  {
    /* Compute how much data to read */
    if ((zs->csiz - zs->inpinf) < INPBUFSIZE)
      inplen = (size_t) (zs->csiz - zs->inpinf);
    else
      inplen = INPBUFSIZE;

    if (inplen <= 0) return TRUE;

    /* Read some data from the file */
    ret = fread(zs->inpbuf, 1, inplen, zs->OpenFile);
    if (ret != inplen) return TRUE;

    /* Update how much data has been read from the file */
    zs->inpinf += inplen;

    /* Pump this data into the decompressor */
    if (InflatePutBuffer(zs->inflatestate, zs->inpbuf, inplen)) return TRUE;
  }

  return FALSE;
}

/* Read from the buffer */
static int BufferRead(
  struct ZipioState *zs,
  long offset,
  unsigned char *buffer,
  long length
)
{
  /*
   * Make sure enough bytes have been inflated
   * Note that the correction for reading past EOF has to
   * be done before calling this routine
   */

  if (BufferPump(zs, offset+length)) return TRUE;

  /* If using file buffering, just get the data from the file */
  if (zs->tmpfil)
  {
    if (fseek(zs->tmpfil, offset, SEEK_SET)) return TRUE;
    if (fread(buffer, 1, (size_t) length, zs->tmpfil) != length) return TRUE;
  }
  /* If no temp file, use memory buffering */
  else
  {
    unsigned int i;
    unsigned int off, len;
    unsigned char *ptr;

    long           tmpoff;
    unsigned char *tmpbuf;
    long           tmplen;

    /* Save copies of offset, buffer and length for the loop */
    tmpoff = offset;
    tmpbuf = buffer;
    tmplen = length;

    /* Validate the transfer */
    if (tmpoff+tmplen > MAXFILESIZE) return TRUE;

    /* Loop till done */
    while (tmplen)
    {
      /* Get a pointer to the next block */
      i = (unsigned int) (tmpoff / OUTBUFSIZE);
      ptr = zs->ptrbuf[i];
      if (!ptr) return TRUE;

      /* Get the offset,length for this block */
      off = (unsigned int) (tmpoff & (OUTBUFSIZE-1));
      len = OUTBUFSIZE - off;
      if (len > tmplen) len = (unsigned int) tmplen;

      /* Get the starting pointer for the transfer */
      ptr += off;

      /* Copy the data for this block */
#ifdef MEMCPY
      memcpy(tmpbuf, ptr, len);
#else
      for (i=0; i<len; i++)
        tmpbuf[i] = ptr[i];
#endif

      /* Update the offset, buffer, and length */
      tmpoff += len;
      tmpbuf += len;
      tmplen -= len;
    }
  }

  /* return success */
  return FALSE;
}

/* Append to the buffer */
static int BufferAppend(
  struct ZipioState *zs,
  unsigned char *buffer,
  long length
)
{
  /* If using file buffering, just append the data from the file */
  if (zs->tmpfil)
  {
    if (fseek(zs->tmpfil, zs->outinf, SEEK_SET)) return TRUE;
    if (fwrite(buffer, 1, (size_t) length, zs->tmpfil) != length) return TRUE;
  }
  /* If no temp file, use memory buffering */
  else
  {
    unsigned int i;
    unsigned int off, len;
    unsigned char *ptr;

    long           tmpoff;
    unsigned char *tmpbuf;
    long           tmplen;

    /* Save copies of outinf, buffer and length for the loop */
    tmpoff = zs->outinf;
    tmpbuf = buffer;
    tmplen = length;

    /* Validate the transfer */
    if (tmpoff+tmplen > MAXFILESIZE) return TRUE;

    /* Loop till done */
    while (tmplen)
    {
      /* Get a pointer to the next block */
      i = (unsigned int) (tmpoff / OUTBUFSIZE);
      ptr = zs->ptrbuf[i];
      if (!ptr)
      {
        ptr = (unsigned char *) malloc(OUTBUFSIZE);
        if (!ptr) return TRUE;
        zs->ptrbuf[i] = ptr;
      }

      /* Get the offset,length for this block */
      off = (unsigned int) (tmpoff & (OUTBUFSIZE-1));
      len = OUTBUFSIZE - off;
      if (len > tmplen) len = (unsigned int) tmplen;

      /* Get the starting pointer for the transfer */
      ptr += off;

      /* Copy the data for this block */
#ifdef MEMCPY
      memcpy(ptr, tmpbuf, len);
#else
      for (i=0; i<len; i++)
        ptr[i] = tmpbuf[i];
#endif

      /* Update the offset, buffer, and length */
      tmpoff += len;
      tmpbuf += len;
      tmplen -= len;
    }
  }

  /* Update the output buffer length */
  zs->outinf += length;

  /* return success */
  return FALSE;
}

/* Terminate buffering */
static void BufferTerminate(
  struct ZipioState *zs
)
{
  /* If reading directly from the uncompressed file, just mark with NULL */
  if (zs->tmpfil == zs->OpenFile)
  {
    zs->tmpfil = NULL;
  }
  /* If using the a temporary file, close it */
  else if (zs->tmpfil)
  {
    fclose(zs->tmpfil);
    zs->tmpfil = NULL;
  }
  /* If doing memory buffering, free the buffers */
  else
  {
    int i;

    for (i=0; i<PTRBUFSIZE; i++)
      if (zs->ptrbuf[i]) free(zs->ptrbuf[i]);
  }
}

/*
 * callout routines for InflateInitialize
 */

static int inflate_putbuffer(             /* returns 0 on success       */
    void *stream,                         /* opaque ptr from Initialize */
    unsigned char *buffer,                /* buffer to put              */
    long length                           /* length of buffer           */
)
{
  RUNTIMECHECK;

  /* If the write will go past the end of file, return an error */
  if (ZS->outinf + length > ZS->usiz) return TRUE;

  /* Update the CRC */
  ZS->filecrc = CrcUpdate(ZS->filecrc, buffer, length);

  /* Append to the buffer */
  if (BufferAppend(ZS, buffer, length)) return TRUE;

  /* Return success */
  return FALSE;
}

static void *inflate_malloc(long length)
{
  return malloc((size_t) length);
}

static void inflate_free(void *buffer)
{
  free(buffer);
}

ZFILE *Zopen(const char *path, const char *mode)
{
  struct ZipioState *zs;

  long inplen;

  /* Allocate the ZipioState memory area */
  zs = (struct ZipioState *) malloc(sizeof(struct ZipioState));
  if (!zs) return NULL;

  /* Set up the initial values of the inflate state */

  CACHEINIT;

  RUNTIMEINIT;

  zs->errorencountered = FALSE;

  zs->inpinf           = 0;
  zs->outinf           = 0;

  zs->fileposition     = 0;

  zs->filecrc          = 0xffffffffL;

  /* Open the real file */
  zs->OpenFile = fopen(path, mode);
  if (!zs->OpenFile)
  {
    free(zs);
    return NULL;
  }

  /* Read the first input buffer */
  if ((inplen = (long) fread(zs->inpbuf, 1, INPBUFSIZE, zs->OpenFile)) >= 30)
  {
    GETUINT4(zs->inpbuf+ 0, zs->sign);
    GETUINT2(zs->inpbuf+ 4, zs->vers);
    GETUINT2(zs->inpbuf+ 6, zs->flag);
    GETUINT2(zs->inpbuf+ 8, zs->comp);
    GETUINT2(zs->inpbuf+10, zs->mtim);
    GETUINT2(zs->inpbuf+12, zs->mdat);
    GETUINT4(zs->inpbuf+14, zs->crc3);
    GETUINT4(zs->inpbuf+18, zs->csiz);
    GETUINT4(zs->inpbuf+22, zs->usiz);
    GETUINT2(zs->inpbuf+26, zs->flen);
    GETUINT2(zs->inpbuf+28, zs->elen);

#ifdef PRINTZIPHEADER
    fprintf(stderr, "local file header signature  hex %8lx\n", zs->sign);
    fprintf(stderr, "version needed to extract        %8d\n" , zs->vers);
    fprintf(stderr, "general purpose bit flag     hex %8x\n" , zs->flag);
    fprintf(stderr, "compression method               %8d\n" , zs->comp);
    fprintf(stderr, "last mod file time               %8d\n" , zs->mtim);
    fprintf(stderr, "last mod file date               %8d\n" , zs->mdat);
    fprintf(stderr, "crc-32                       hex %8lx\n", zs->crc3);
    fprintf(stderr, "compressed size                  %8ld\n", zs->csiz);
    fprintf(stderr, "uncompressed size                %8ld\n", zs->usiz);
    fprintf(stderr, "filename length                  %8d\n" , zs->flen);
    fprintf(stderr, "extra field length               %8d\n" , zs->elen);
#endif
  }
  else
  {
    zs->sign = 0;
  }

  /*
   * If the file isn't a zip file, set up to read it normally
   */
  if ((zs->sign   !=             ZIPSIGNATURE) ||
      (zs->flag   &                         1) ||
      (zs->comp   !=                        8) ||
      (inplen     <= 30 + zs->flen + zs->elen)    )
  {
    /* Initialize buffering */
    BufferInitialize(zs, FALSE);

    zs->inflatestate = NULL;
  }
  else
  {
    /* Initialize buffering */
    BufferInitialize(zs, TRUE);

    zs->inflatestate = InflateInitialize(
                         (void *) zs,
                         inflate_putbuffer,
                         inflate_malloc,
                         inflate_free
                       );

    if (InflatePutBuffer(zs->inflatestate,
                         zs->inpbuf+30+zs->flen+zs->elen,
                             inplen-30-zs->flen-zs->elen
                        )
       )
      zs->errorencountered = TRUE;

    zs->inpinf += inplen-30-zs->flen-zs->elen;
  }

  /* Return this state info to the caller */
  return (ZFILE *) zs;
}

int _Zgetc(ZFILE *stream)
{
  long offset, length;

  int off;

  RUNTIMECHECK;

  if (ZS->errorencountered) return -1;

  CACHEUPDATE;

  /* If already at EOF, return */
  if (ZS->fileposition >= ZS->usiz) return -1;

  /* If data isn't in current outbuf, get it */
  offset = ZS->fileposition & ~((long) (OUTBUFSIZE-1));
  length = ZS->usiz - offset;
  if (length > OUTBUFSIZE) length = OUTBUFSIZE;

  if (ZS->getoff != offset)
  {
    if (BufferRead(ZS, offset, ZS->getbuf, length)) return -1;

    ZS->getoff = offset;
  }

  /* Set up the cache */
  off = (int) (ZS->fileposition & (OUTBUFSIZE-1));
  ZS->len = (int) (length - off);
  ZS->ptr = ZS->getbuf    + off;

  /* Return the character */
           ZS->len--;
  return *(ZS->ptr++);
}

size_t Zread(void *ptr, size_t size, size_t n, ZFILE *stream)
{
  long           length;

  RUNTIMECHECK;

  if (ZS->errorencountered) return 0;

  CACHEUPDATE;

  /* Compute the length requested */
  length = size * (long) n;

  /* Adjust the length to account for premature EOF */
  if (ZS->fileposition+length > ZS->usiz)
    length = ZS->usiz - ZS->fileposition;

  /* If the length is zero, then just return an EOF error */
  if (length <= 0) return 0;

  /* Make the length a multiple of size */
  length /= size;
  length *= size;

  /* If the length is zero, then just return an EOF error */
  if (length <= 0) return 0;

  /* Read from the buffer */
  if (BufferRead(ZS, ZS->fileposition, (unsigned char *) ptr, length))
    return 0;

  /* Update the file position */
  ZS->fileposition += length;

  /* Return the number of items transferred */
  return (size_t) (length / size);
}

int Zseek(ZFILE *stream, long offset, int whence)
{
  long newoffset;

  RUNTIMECHECK;

  if (ZS->errorencountered) return -1;

  CACHEUPDATE;

  if (whence == SEEK_SET)
  {
    newoffset = offset;
  }
  else if (whence == SEEK_CUR)
  {
    newoffset = ZS->fileposition + offset;
  }
  else if (whence == SEEK_END)
  {
    newoffset = ZS->fileposition + ZS->usiz;
  }
  else
  {
    return -1;
  }

  if ((newoffset < 0) || (newoffset > ZS->usiz)) return -1;

  ZS->fileposition = newoffset;

  return 0;
}

long Ztell(ZFILE *stream)
{
  RUNTIMECHECK;

  if (ZS->errorencountered) return -1;

  CACHEUPDATE;

  return ZS->fileposition;
}

int Zclose(ZFILE *stream)
{
  int ret;

  RUNTIMECHECK;

  CACHEUPDATE;

  /* terminate the inflate routines, and check for errors */
  if (ZS->inflatestate)
  {
    if (InflateTerminate(ZS->inflatestate))
      ZS->errorencountered = TRUE;

    /* Check that the CRC is OK */
    if (ZS->filecrc != (ZS->crc3 ^ 0xffffffffL))
      ZS->errorencountered = TRUE;
  }

  /* save the final error status */
  ret = ZS->errorencountered;

  /* terminate the buffering */
  BufferTerminate(ZS);

  /* free the ZipioState structure */
  free(ZS);

  /* return the final error status */
  return ret;
}
