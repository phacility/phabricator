/*
 * inflate.c -  inflate decompression routine
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
 * inflate.c is based on the public-domain (non-copyrighted) version
 * written by Mark Adler, version c14o, 23 August 1994.  It has been 
 * modified to be reentrant, more portable, and to be data driven.
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
 * 0 or more calls to (*putbuffer_ptr).  Before InflatePutBuffer
 * returns, it will have output as much uncompressed data as
 * is possible.
 */

#ifdef MEMCPY
#include <mem.h>
#endif

#include "inflate.h"

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

#ifndef WINDOWSIZE
#define WINDOWSIZE 0x8000
#endif

#ifndef WINDOWMASK
#define WINDOWMASK 0x7fff
#endif

#ifndef BUFFERSIZE
#define BUFFERSIZE 0x4000
#endif

#ifndef BUFFERMASK
#define BUFFERMASK 0x3fff
#endif

#ifndef INFLATESTATETYPE
#define INFLATESTATETYPE   0xabcdabcdL
#endif

/*
 * typedefs
 */

typedef unsigned long  ulg;
typedef unsigned short ush;
typedef unsigned char  uch;

/* Structure to hold state for inflating zip files */
struct InflateState {

  unsigned long  runtimetypeid1;             /* to detect run-time errors  */
  int            errorencountered;           /* error encountered flag     */

  /* Decoding state */
  int            state;                      /* -1 -> need block type      */
                                             /*  0 -> need stored setup    */
                                             /*  1 -> need fixed setup     */
                                             /*  2 -> need dynamic setup   */
                                             /* 10 -> need stored data     */
                                             /* 11 -> need fixed data      */
                                             /* 12 -> need dynamic data    */

/* State for decoding fixed & dynamic data */
  struct huft   *tl;                         /* literal/length decoder tbl */
  struct huft   *td;                         /* distance decoder table     */
  int            bl;                         /* bits decoded by tl         */
  int            bd;                         /* bits decoded by td         */

  /* State for decoding stored data */
  unsigned int   storelength;

  /* State to keep track that last block has been encountered */
  int            lastblock;                  /* current block is last      */

  /* Input buffer state (circular) */
  ulg            bb;                         /* input buffer bits          */
  unsigned int   bk;                         /* input buffer count of bits */
  unsigned int   bp;                         /* input buffer pointer       */
  unsigned int   bs;                         /* input buffer size          */
  unsigned char  buffer[BUFFERSIZE];         /* input buffer data          */

  /* Storage for try/catch */
  ulg            catch_bb;                   /* bit buffer                 */
  unsigned int   catch_bk;                   /* bits in bit buffer         */
  unsigned int   catch_bp;                   /* buffer pointer             */
  unsigned int   catch_bs;                   /* buffer size                */

  /* Output window state (circular) */
  unsigned int   wp;                         /* output window pointer      */
  unsigned int   wf;                         /* output window flush-from   */
  unsigned char  window[WINDOWSIZE];         /* output window data         */

  /* Application state */
  void          *AppState;                   /* opaque ptr for callout     */

  /* pointers to call-outs */
  int (*putbuffer_ptr)(                      /* returns 0 on success       */
    void *AppState,                          /* opaque ptr from Initialize */
    unsigned char *buffer,                   /* buffer to put              */
    long length                              /* length of buffer           */
  );

  void *(*malloc_ptr)(long length);          /* utility routine            */

  void (*free_ptr)(void *buffer);            /* utility routine            */

  unsigned long  runtimetypeid2;             /* to detect run-time errors  */
};

/*
 * Error handling macro
 */

#define ERROREXIT(is) {(is)->errorencountered = TRUE; return TRUE;}

/*
 * Macros for handling data in the input buffer
 *
 * Note that the NEEDBITS and DUMPBITS macros
 * need to be bracketed by the TRY/CATCH macros
 *
 * The usage is:
 *
 *      TRY
 *      {
 *        NEEDBITS(j)
 *        x = b & mask_bits[j];
 *        DUMPBITS(j)
 *      }
 *      CATCH_BEGIN
 *        cleanup code
 *      CATCH_END
 *
 * Note that there can only be one TRY/CATCH pair per routine
 * because of the use of goto in the implementation of the macros.
 *
 * NEEDBITS makes sure that b has at least j bits in it, and
 * DUMPBITS removes the bits from b.  The macros use the variable k
 * for the number of bits in b.  Normally, b and k are register
 * variables for speed, and are initialized at the beginning of a
 * routine that uses these macros from a global bit buffer and count.
 *
 * In order to not ask for more bits than there are in the compressed
 * stream, the Huffman tables are constructed to only ask for just
 * enough bits to make up the end-of-block code (value 256).  Then no
 * bytes need to be "returned" to the buffer at the end of the last
 * block.  See the huft_build() routine.
 */

#define TRY              \
  is->catch_bb = b;      \
  is->catch_bk = k;      \
  is->catch_bp = is->bp; \
  is->catch_bs = is->bs;

#define CATCH_BEGIN      \
  goto cleanup_done;     \
  cleanup:               \
  b      = is->catch_bb; \
  k      = is->catch_bk; \
  is->bb = b;            \
  is->bk = k;            \
  is->bp = is->catch_bp; \
  is->bs = is->catch_bs;

#define CATCH_END \
  cleanup_done: ;

#define NEEDBITS(n)                                      \
{                                                        \
  while (k < (n))                                        \
  {                                                      \
    if (is->bs <= 0)                                     \
    {                                                    \
      goto cleanup;                                      \
    }                                                    \
    b |= ((ulg) (is->buffer[is->bp & BUFFERMASK])) << k; \
    is->bs--;                                            \
    is->bp++;                                            \
    k += 8;                                              \
  }                                                      \
}

#define DUMPBITS(n) \
{                   \
  b >>= (n);        \
  k -= (n);         \
}

/*
 * Macro for flushing the output window to the putbuffer callout.
 *
 * Note that the window is always flushed when it fills to 32K,
 * and before returning to the application.
 */

#define FLUSHWINDOW(w, now)                               \
if ((now && (is->wp > is->wf)) || ((w) >= WINDOWSIZE))    \
{                                                         \
  is->wp = (w);                                           \
  if ((*(is->putbuffer_ptr))                              \
        (is->AppState, is->window+is->wf, is->wp-is->wf)) \
    ERROREXIT(is);                                        \
  is->wp &= WINDOWMASK;                                   \
  is->wf  = is->wp;                                       \
  (w) = is->wp;                                           \
}

/*
 * Inflate deflated (PKZIP's method 8 compressed) data.  The compression
 * method searches for as much of the current string of bytes (up to a
 * length of 258) in the previous 32K bytes.  If it doesn't find any
 * matches (of at least length 3), it codes the next byte.  Otherwise, it
 * codes the length of the matched string and its distance backwards from
 * the current position.  There is a single Huffman code that codes both
 * single bytes (called "literals") and match lengths.  A second Huffman
 * code codes the distance information, which follows a length code.  Each
 * length or distance code actually represents a base value and a number
 * of "extra" (sometimes zero) bits to get to add to the base value.  At
 * the end of each deflated block is a special end-of-block (EOB) literal/
 * length code.  The decoding process is basically: get a literal/length
 * code; if EOB then done; if a literal, emit the decoded byte; if a
 * length then get the distance and emit the referred-to bytes from the
 * sliding window of previously emitted data.
 *
 * There are (currently) three kinds of inflate blocks: stored, fixed, and
 * dynamic.  The compressor outputs a chunk of data at a time and decides
 * which method to use on a chunk-by-chunk basis.  A chunk might typically
 * be 32K to 64K, uncompressed.  If the chunk is uncompressible, then the
 * "stored" method is used.  In this case, the bytes are simply stored as
 * is, eight bits per byte, with none of the above coding.  The bytes are
 * preceded by a count, since there is no longer an EOB code.
 *
 * If the data is compressible, then either the fixed or dynamic methods
 * are used.  In the dynamic method, the compressed data is preceded by
 * an encoding of the literal/length and distance Huffman codes that are
 * to be used to decode this block.  The representation is itself Huffman
 * coded, and so is preceded by a description of that code.  These code
 * descriptions take up a little space, and so for small blocks, there is
 * a predefined set of codes, called the fixed codes.  The fixed method is
 * used if the block ends up smaller that way (usually for quite small
 * chunks); otherwise the dynamic method is used.  In the latter case, the
 * codes are customized to the probabilities in the current block and so
 * can code it much better than the pre-determined fixed codes can.
 *
 * The Huffman codes themselves are decoded using a mutli-level table
 * lookup, in order to maximize the speed of decoding plus the speed of
 * building the decoding tables.  See the comments below that precede the
 * lbits and dbits tuning parameters.
 */

/*
 * Notes beyond the 1.93a appnote.txt:
 *
 * 1. Distance pointers never point before the beginning of the output
 *    stream.
 * 2. Distance pointers can point back across blocks, up to 32k away.
 * 3. There is an implied maximum of 7 bits for the bit length table and
 *    15 bits for the actual data.
 * 4. If only one code exists, then it is encoded using one bit.  (Zero
 *    would be more efficient, but perhaps a little confusing.)  If two
 *    codes exist, they are coded using one bit each (0 and 1).
 * 5. There is no way of sending zero distance codes--a dummy must be
 *    sent if there are none.  (History: a pre 2.0 version of PKZIP would
 *    store blocks with no distance codes, but this was discovered to be
 *    too harsh a criterion.)  Valid only for 1.93a.  2.04c does allow
 *    zero distance codes, which is sent as one code of zero bits in
 *    length.
 * 6. There are up to 286 literal/length codes.  Code 256 represents the
 *    end-of-block.  Note however that the static length tree defines
 *    288 codes just to fill out the Huffman codes.  Codes 286 and 287
 *    cannot be used though, since there is no length base or extra bits
 *    defined for them.  Similarly, there are up to 30 distance codes.
 *    However, static trees define 32 codes (all 5 bits) to fill out the
 *    Huffman codes, but the last two had better not show up in the data.
 * 7. Unzip can check dynamic Huffman blocks for complete code sets.
 *    The exception is that a single code would not be complete (see #4).
 * 8. The five bits following the block type is really the number of
 *    literal codes sent minus 257.
 * 9. Length codes 8,16,16 are interpreted as 13 length codes of 8 bits
 *    (1+6+6).  Therefore, to output three times the length, you output
 *    three codes (1+1+1), whereas to output four times the same length,
 *    you only need two codes (1+3).  Hmm.
 *10. In the tree reconstruction algorithm, Code = Code + Increment
 *    only if BitLength(i) is not zero.  (Pretty obvious.)
 *11. Correction: 4 Bits: # of Bit Length codes - 4     (4 - 19)
 *12. Note: length code 284 can represent 227-258, but length code 285
 *    really is 258.  The last length deserves its own, short code
 *    since it gets used a lot in very redundant files.  The length
 *    258 is special since 258 - 3 (the min match length) is 255.
 *13. The literal/length and distance code bit lengths are read as a
 *    single stream of lengths.  It is possible (and advantageous) for
 *    a repeat code (16, 17, or 18) to go across the boundary between
 *    the two sets of lengths.
 */

/*
 * Huffman code lookup table entry--this entry is four bytes for machines
 * that have 16-bit pointers (e.g. PC's in the small or medium model).
 * Valid extra bits are 0..13.  e == 15 is EOB (end of block), e == 16
 * means that v is a literal, 16 < e < 32 means that v is a pointer to
 * the next table, which codes e - 16 bits, and lastly e == 99 indicates
 * an unused code.  If a code with e == 99 is looked up, this implies an
 * error in the data.
 */

struct huft {
  uch e;                /* number of extra bits or operation */
  uch b;                /* number of bits in this code or subcode */
  union {
    ush n;              /* literal, length base, or distance base */
    struct huft *t;     /* pointer to next level of table */
  } v;
};

/*
 * Tables for deflate from PKZIP's appnote.txt.
 */

static const unsigned border[] = { /* Order of the bit length code lengths */
        16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15};

static const ush cplens[] = {      /* Copy lengths for literal codes 257..285 */
        3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 19, 23, 27, 31,
        35, 43, 51, 59, 67, 83, 99, 115, 131, 163, 195, 227, 258, 0, 0};
        /* note: see note #13 above about the 258 in this list. */

static const ush cplext[] = {      /* Extra bits for literal codes 257..285 */
        0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2,
        3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 0, 99, 99}; /* 99==invalid */

static const ush cpdist[] = {      /* Copy offsets for distance codes 0..29 */
        1, 2, 3, 4, 5, 7, 9, 13, 17, 25, 33, 49, 65, 97, 129, 193,
        257, 385, 513, 769, 1025, 1537, 2049, 3073, 4097, 6145,
        8193, 12289, 16385, 24577};

static const ush cpdext[] = {      /* Extra bits for distance codes */
        0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6,
        7, 7, 8, 8, 9, 9, 10, 10, 11, 11,
        12, 12, 13, 13};

/*
 * Constants for run-time computation of mask
 */

static const ush mask_bits[] = {
    0x0000,
    0x0001, 0x0003, 0x0007, 0x000f, 0x001f, 0x003f, 0x007f, 0x00ff,
    0x01ff, 0x03ff, 0x07ff, 0x0fff, 0x1fff, 0x3fff, 0x7fff, 0xffff
};

/*
 * Huffman code decoding is performed using a multi-level table lookup.
 * The fastest way to decode is to simply build a lookup table whose
 * size is determined by the longest code.  However, the time it takes
 * to build this table can also be a factor if the data being decoded
 * is not very long.  The most common codes are necessarily the
 * shortest codes, so those codes dominate the decoding time, and hence
 * the speed.  The idea is you can have a shorter table that decodes the
 * shorter, more probable codes, and then point to subsidiary tables for
 * the longer codes.  The time it costs to decode the longer codes is
 * then traded against the time it takes to make longer tables.
 *
 * This results of this trade are in the variables lbits and dbits
 * below.  lbits is the number of bits the first level table for literal/
 * length codes can decode in one step, and dbits is the same thing for
 * the distance codes.  Subsequent tables are also less than or equal to
 * those sizes.  These values may be adjusted either when all of the
 * codes are shorter than that, in which case the longest code length in
 * bits is used, or when the shortest code is *longer* than the requested
 * table size, in which case the length of the shortest code in bits is
 * used.
 *
 * There are two different values for the two tables, since they code a
 * different number of possibilities each.  The literal/length table
 * codes 286 possible values, or in a flat code, a little over eight
 * bits.  The distance table codes 30 possible values, or a little less
 * than five bits, flat.  The optimum values for speed end up being
 * about one bit more than those, so lbits is 8+1 and dbits is 5+1.
 * The optimum values may differ though from machine to machine, and
 * possibly even between compilers.  Your mileage may vary.
 */

static const int lbits = 9;  /* bits in base literal/length lookup table */
static const int dbits = 6;  /* bits in base distance lookup table */

/* If BMAX needs to be larger than 16, then h and x[] should be ulg. */
#define BMAX 16         /* maximum bit length of any code (16 for explode) */
#define N_MAX 288       /* maximum number of codes in any set */

/*
 * Free the malloc'ed tables built by huft_build(), which makes a linked
 * list of the tables it made, with the links in a dummy first entry of
 * each table.
 */

static int huft_free(
  struct InflateState *is, /* Inflate state */
  struct huft *t           /* table to free */
)
{
  struct huft *p, *q;

  /* Go through linked list, freeing from the malloced (t[-1]) address. */
  p = t;
  while (p != (struct huft *)NULL)
  {
    q = (--p)->v.t;
    (*is->free_ptr)((char*)p);
    p = q;
  }
  return 0;
}

/*
 * Given a list of code lengths and a maximum table size, make a set of
 * tables to decode that set of codes.  Return zero on success, one if
 * the given code set is incomplete (the tables are still built in this
 * case), two if the input is invalid (all zero length codes or an
 * oversubscribed set of lengths), and three if not enough memory.
 * The code with value 256 is special, and the tables are constructed
 * so that no bits beyond that code are fetched when that code is
 * decoded.
 */

static int huft_build(
  struct InflateState *is, /* Inflate state */
  unsigned *b,             /* code lengths in bits (all assumed <= BMAX) */
  unsigned n,              /* number of codes (assumed <= N_MAX) */
  unsigned s,              /* number of simple-valued codes (0..s-1) */
  const ush *d,            /* list of base values for non-simple codes */
  const ush *e,            /* list of extra bits for non-simple codes */
  struct huft **t,         /* result: starting table */
  int *m                   /* maximum lookup bits, returns actual */
)
{
  unsigned a;                   /* counter for codes of length k */
  unsigned c[BMAX+1];           /* bit length count table */
  unsigned el;                  /* length of EOB code (value 256) */
  unsigned f;                   /* i repeats in table every f entries */
  int g;                        /* maximum code length */
  int h;                        /* table level */
  unsigned i;                   /* counter, current code */
  unsigned j;                   /* counter */
  int k;                        /* number of bits in current code */
  int lx[BMAX+1];               /* memory for l[-1..BMAX-1] */
  int *l = lx+1;                /* stack of bits per table */
  unsigned *p;                  /* pointer into c[], b[], or v[] */
  struct huft *q;               /* points to current table */
  struct huft r;                /* table entry for structure assignment */
  struct huft *u[BMAX];         /* table stack */
  unsigned v[N_MAX];            /* values in order of bit length */
  int w;                        /* bits before this table == (l * h) */
  unsigned x[BMAX+1];           /* bit offsets, then code stack */
  unsigned *xp;                 /* pointer into x */
  int y;                        /* number of dummy codes added */
  unsigned z;                   /* number of entries in current table */

  /* clear the bit length count table */
  for (i=0; i<(BMAX+1); i++)
  {
    c[i] = 0;
  }

  /* Generate counts for each bit length */
  el = n > 256 ? b[256] : BMAX; /* set length of EOB code, if any */
  p = b;  i = n;
  do {
    c[*p]++; p++;               /* assume all entries <= BMAX */
  } while (--i);
  if (c[0] == n)                /* null input--all zero length codes */
  {
    *t = (struct huft *)NULL;
    *m = 0;
    return 0;
  }

  /* Find minimum and maximum length, bound *m by those */
  for (j = 1; j <= BMAX; j++)
    if (c[j])
      break;
  k = j;                        /* minimum code length */
  if ((unsigned)*m < j)
    *m = j;
  for (i = BMAX; i; i--)
    if (c[i])
      break;
  g = i;                        /* maximum code length */
  if ((unsigned)*m > i)
    *m = i;

  /* Adjust last length count to fill out codes, if needed */
  for (y = 1 << j; j < i; j++, y <<= 1)
    if ((y -= c[j]) < 0)
      return 2;                 /* bad input: more codes than bits */
  if ((y -= c[i]) < 0)
    return 2;
  c[i] += y;

  /* Generate starting offsets into the value table for each length */
  x[1] = j = 0;
  p = c + 1;  xp = x + 2;
  while (--i) {                 /* note that i == g from above */
    *xp++ = (j += *p++);
  }

  /* Make a table of values in order of bit lengths */
  p = b;  i = 0;
  do {
    if ((j = *p++) != 0)
      v[x[j]++] = i;
  } while (++i < n);

  /* Generate the Huffman codes and for each, make the table entries */
  x[0] = i = 0;                 /* first Huffman code is zero */
  p = v;                        /* grab values in bit order */
  h = -1;                       /* no tables yet--level -1 */
  w = l[-1] = 0;                /* no bits decoded yet */
  u[0] = (struct huft *)NULL;   /* just to keep compilers happy */
  q = (struct huft *)NULL;      /* ditto */
  z = 0;                        /* ditto */

  /* go through the bit lengths (k already is bits in shortest code) */
  for (; k <= g; k++)
  {
    a = c[k];
    while (a--)
    {
      /* here i is the Huffman code of length k bits for value *p */
      /* make tables up to required level */
      while (k > w + l[h])
      {
        w += l[h++];            /* add bits already decoded */

        /* compute minimum size table less than or equal to *m bits */
        z = (z = g - w) > (unsigned)*m ? *m : z;        /* upper limit */
        if ((f = 1 << (j = k - w)) > a + 1)     /* try a k-w bit table */
        {                       /* too few codes for k-w bit table */
          f -= a + 1;           /* deduct codes from patterns left */
          xp = c + k;
          while (++j < z)       /* try smaller tables up to z bits */
          {
            if ((f <<= 1) <= *++xp)
              break;            /* enough codes to use up j bits */
            f -= *xp;           /* else deduct codes from patterns */
          }
        }
        if ((unsigned)w + j > el && (unsigned)w < el)
          j = el - w;           /* make EOB code end at table */
        z = 1 << j;             /* table entries for j-bit table */
        l[h] = j;               /* set table size in stack */

        /* allocate and link in new table */
        if ((q = (struct huft *)
                   ((*is->malloc_ptr)((z + 1)*sizeof(struct huft)))) ==
                 (struct huft *)NULL)
        {
          if (h)
            huft_free(is, u[0]);
          return 3;             /* not enough memory */
        }
        *t = q + 1;             /* link to list for huft_free() */
        *(t = &(q->v.t)) = (struct huft *)NULL;
        u[h] = ++q;             /* table starts after link */

        /* connect to last table, if there is one */
        if (h)
        {
          x[h] = i;             /* save pattern for backing up */
          r.b = (uch)l[h-1];    /* bits to dump before this table */
          r.e = (uch)(16 + j);  /* bits in this table */
          r.v.t = q;            /* pointer to this table */
          j = (i & ((1 << w) - 1)) >> (w - l[h-1]);
          u[h-1][j] = r;        /* connect to last table */
        }
      }

      /* set up table entry in r */
      r.b = (uch)(k - w);
      if (p >= v + n)
        r.e = 99;               /* out of values--invalid code */
      else if (*p < s)
      {
        r.e = (uch)(*p < 256 ? 16 : 15);    /* 256 is end-of-block code */
        r.v.n = (ush) *p++;                 /* simple code is just the value */
      }
      else
      {
        r.e = (uch)e[*p - s];   /* non-simple--look up in lists */
        r.v.n = d[*p++ - s];
      }

      /* fill code-like entries with r */
      f = 1 << (k - w);
      for (j = i >> w; j < z; j += f)
        q[j] = r;

      /* backwards increment the k-bit code i */
      for (j = 1 << (k - 1); i & j; j >>= 1)
        i ^= j;
      i ^= j;

      /* backup over finished tables */
      while ((i & ((1 << w) - 1)) != x[h])
        w -= l[--h];            /* don't need to update q */
    }
  }

  /* return actual size of base table */
  *m = l[0];

  /* Return true (1) if we were given an incomplete table */
  return y != 0 && g != 1;
}

/*
 * inflate (decompress) the codes in a stored (uncompressed) block.
 * Return an error code or zero if it all goes ok.
 */

static int inflate_stored(
  struct InflateState *is  /* Inflate state */
)
{
  ulg b;                /* bit buffer */
  unsigned k;           /* number of bits in bit buffer */
  unsigned w;           /* current window position */

  /* make local copies of state */
  b = is->bb;           /* initialize bit buffer */
  k = is->bk;           /* initialize bit count */
  w = is->wp;           /* initialize window position */

  /*
   * Note that this code knows that NEEDBITS jumps to cleanup
   */

  while (is->storelength > 0)  /* do until end of block */
  {
    NEEDBITS(8)
    is->window[w++] = (uch) b;
    DUMPBITS(8)
    FLUSHWINDOW(w, FALSE);
    is->storelength--;
  }

  cleanup:

  /* restore the state from the locals */
  is->bb = b;              /* restore bit buffer */
  is->bk = k;              /* restore bit count */
  is->wp = w;              /* restore window pointer */

  if (is->storelength > 0)
    return -1;
  else
    return 0;
}

static int inflate_codes(
  struct InflateState *is, /* Inflate state */
  struct huft *tl,         /* literal/length decoder table */
  struct huft *td,         /* distance decoder table */
  int bl,                  /* number of bits decoded by tl[] */
  int bd                   /* number of bits decoded by td[] */
)
{
  unsigned e;           /* table entry flag/number of extra bits */
  unsigned n, d;        /* length and index for copy */
  unsigned w;           /* current window position */
  struct huft *t;       /* pointer to table entry */
  unsigned ml, md;      /* masks for bl and bd bits */
  ulg b;                /* bit buffer */
  unsigned k;           /* number of bits in bit buffer */

  /* make local copies of state */
  b = is->bb;           /* initialize bit buffer */
  k = is->bk;           /* initialize bit count */
  w = is->wp;           /* initialize window position */

  /* inflate the coded data */
  ml = mask_bits[bl];           /* precompute masks for speed */
  md = mask_bits[bd];
  for (;;)                      /* do until end of block */
  {
    TRY
    {
      NEEDBITS((unsigned)bl)
      if ((e = (t = tl + ((unsigned)b & ml))->e) > 16)
        do {
          if (e == 99)
            return 1;
          DUMPBITS(t->b)
          e -= 16;
          NEEDBITS(e)
        } while ((e = (t = t->v.t + ((unsigned)b & mask_bits[e]))->e) > 16);
      DUMPBITS(t->b)

      if (e == 16)                /* it's a literal */
      {
        is->window[w++] = (uch)t->v.n;
        FLUSHWINDOW(w, FALSE);
      }
      else if (e == 15)           /* it's an EOB */
      {
        break;
      }
      else                        /* it's a length */
      {
        /* get length of block to copy */
        NEEDBITS(e)
        n = t->v.n + ((unsigned)b & mask_bits[e]);
        DUMPBITS(e);

        /* decode distance of block to copy */
        NEEDBITS((unsigned)bd)
        if ((e = (t = td + ((unsigned)b & md))->e) > 16)
          do {
            if (e == 99)
              return 1;
            DUMPBITS(t->b)
            e -= 16;
            NEEDBITS(e)
          } while ((e = (t = t->v.t + ((unsigned)b & mask_bits[e]))->e) > 16);
        DUMPBITS(t->b)
        NEEDBITS(e)
        d = w - t->v.n - ((unsigned)b & mask_bits[e]);
        DUMPBITS(e)

        /* do the copy */
        do {
          n -= (e = ((e = WINDOWSIZE - ((d &= WINDOWMASK) > w ? d : w)) > n)
                    ? n : e
               );
#if defined(MEMCPY)
          if (w - d >= e)         /* (this test assumes unsigned comparison) */
          {
            memcpy(is->window + w, is->window + d, e);
            w += e;
            d += e;
          }
          else                      /* do it slow to avoid memcpy() overlap */
#endif /* MEMCPY */
            do {
              is->window[w++] = is->window[d++];
            } while (--e);
          FLUSHWINDOW(w, FALSE);
        } while (n);
      }
    }
    CATCH_BEGIN
      is->wp = w;          /* restore window pointer */
      return -1;
    CATCH_END
  }

  /* restore the state from the locals */
  is->bb = b;              /* restore bit buffer */
  is->bk = k;              /* restore bit count */
  is->wp = w;              /* restore window pointer */

  /* done */
  return 0;
}

/*
 * "decompress" an inflated type 0 (stored) block.
 */

static int inflate_stored_setup(
  struct InflateState *is  /* Inflate state */
)
{
  unsigned n;           /* number of bytes in block */
  ulg b;                /* bit buffer */
  unsigned k;           /* number of bits in bit buffer */

  /* make local copies of state */
  b = is->bb;           /* initialize bit buffer */
  k = is->bk;           /* initialize bit count */

  TRY
  {
    /* go to byte boundary */
    n = k & 7;
    DUMPBITS(n);

    /* get the length and its complement */
    NEEDBITS(16)
    n = ((unsigned)b & 0xffff);
    DUMPBITS(16)
    NEEDBITS(16)
    if (n != (unsigned)((~b) & 0xffff))
      return 1;                   /* error in compressed data */
    DUMPBITS(16)
  }
  CATCH_BEGIN
    return -1;
  CATCH_END

  /* Save store state for this block */
  is->storelength = n;

  /* restore the state from the locals */
  is->bb = b;              /* restore bit buffer */
  is->bk = k;              /* restore bit count */
  
  return 0;
}

/*
 * decompress an inflated type 1 (fixed Huffman codes) block.  We should
 * either replace this with a custom decoder, or at least precompute the
 * Huffman tables.
 */

static int inflate_fixed_setup(
  struct InflateState *is  /* Inflate state */
)
{
  int i;                /* temporary variable */
  struct huft *tl;      /* literal/length code table */
  struct huft *td;      /* distance code table */
  int bl;               /* lookup bits for tl */
  int bd;               /* lookup bits for td */
  unsigned l[288];      /* length list for huft_build */

  /* set up literal table */
  for (i = 0; i < 144; i++)
    l[i] = 8;
  for (; i < 256; i++)
    l[i] = 9;
  for (; i < 280; i++)
    l[i] = 7;
  for (; i < 288; i++)          /* make a complete, but wrong code set */
    l[i] = 8;
  bl = 7;
  if ((i = huft_build(is, l, 288, 257, cplens, cplext, &tl, &bl)) != 0)
    return i;

  /* set up distance table */
  for (i = 0; i < 30; i++)      /* make an incomplete code set */
    l[i] = 5;
  bd = 5;
  if ((i = huft_build(is, l, 30, 0, cpdist, cpdext, &td, &bd)) > 1)
  {
    huft_free(is, tl);
    return i;
  }

  /* Save inflate state for this block */
  is->tl = tl;
  is->td = td;
  is->bl = bl;
  is->bd = bd;

  return 0;
}

/*
 * decompress an inflated type 2 (dynamic Huffman codes) block.
 */

#define PKZIP_BUG_WORKAROUND

static int inflate_dynamic_setup(
  struct InflateState *is  /* Inflate state */
)
{
  int i;                /* temporary variables */
  unsigned j;
  unsigned l;           /* last length */
  unsigned m;           /* mask for bit lengths table */
  unsigned n;           /* number of lengths to get */
  struct huft *tl;      /* literal/length code table */
  struct huft *td;      /* distance code table */
  int bl;               /* lookup bits for tl */
  int bd;               /* lookup bits for td */
  unsigned nb;          /* number of bit length codes */
  unsigned nl;          /* number of literal/length codes */
  unsigned nd;          /* number of distance codes */
#ifdef PKZIP_BUG_WORKAROUND
  unsigned ll[288+32];  /* literal/length and distance code lengths */
#else
  unsigned ll[286+30];  /* literal/length and distance code lengths */
#endif
  ulg b;                /* bit buffer */
  unsigned k;           /* number of bits in bit buffer */

  /* make local copies of state */
  b = is->bb;           /* initialize bit buffer */
  k = is->bk;           /* initialize bit count */

  /* initialize tl for cleanup */
  tl = NULL;

  TRY
  {
    /* read in table lengths */
    NEEDBITS(5)
    nl = 257 + ((unsigned)b & 0x1f);      /* number of literal/length codes */
    DUMPBITS(5)
    NEEDBITS(5)
    nd = 1 + ((unsigned)b & 0x1f);        /* number of distance codes */
    DUMPBITS(5)
    NEEDBITS(4)
    nb = 4 + ((unsigned)b & 0xf);         /* number of bit length codes */
    DUMPBITS(4)
#ifdef PKZIP_BUG_WORKAROUND
    if (nl > 288 || nd > 32)
#else
    if (nl > 286 || nd > 30)
#endif
      return 1;                   /* bad lengths */

    /* read in bit-length-code lengths */
    for (j = 0; j < 19; j++) ll[j] = 0;
    for (j = 0; j < nb; j++)
    {
      NEEDBITS(3)
      ll[border[j]] = (unsigned)b & 7;
      DUMPBITS(3)
    }

    /* build decoding table for trees--single level, 7 bit lookup */
    bl = 7;
    if ((i = huft_build(is, ll, 19, 19, NULL, NULL, &tl, &bl)) != 0)
    {
      if (i == 1)
        huft_free(is, tl);
      return i;                   /* incomplete code set */
    }

    /* read in literal and distance code lengths */
    n = nl + nd;
    m = mask_bits[bl];
    i = l = 0;
    while ((unsigned)i < n)
    {
      NEEDBITS((unsigned)bl)
      j = (td = tl + ((unsigned)b & m))->b;
      DUMPBITS(j)
      j = td->v.n;
      if (j < 16)                 /* length of code in bits (0..15) */
        ll[i++] = l = j;          /* save last length in l */
      else if (j == 16)           /* repeat last length 3 to 6 times */
      {
        NEEDBITS(2)
        j = 3 + ((unsigned)b & 3);
        DUMPBITS(2)
        if ((unsigned)i + j > n)
          return 1;
        while (j--)
          ll[i++] = l;
      }
      else if (j == 17)           /* 3 to 10 zero length codes */
      {
        NEEDBITS(3)
        j = 3 + ((unsigned)b & 7);
        DUMPBITS(3)
        if ((unsigned)i + j > n)
          return 1;
        while (j--)
          ll[i++] = 0;
        l = 0;
      }
      else                        /* j == 18: 11 to 138 zero length codes */
      {
        NEEDBITS(7)
        j = 11 + ((unsigned)b & 0x7f);
        DUMPBITS(7)
        if ((unsigned)i + j > n)
          return 1;
        while (j--)
          ll[i++] = 0;
        l = 0;
      }
    }

    /* free decoding table for trees */
    huft_free(is, tl);
  }
  CATCH_BEGIN
    if (tl) huft_free(is, tl);
    return -1;
  CATCH_END

  /* restore the state from the locals */
  is->bb = b;              /* restore bit buffer */
  is->bk = k;              /* restore bit count */

  /* build the decoding tables for literal/length and distance codes */
  bl = lbits;
  if ((i = huft_build(is, ll, nl, 257, cplens, cplext, &tl, &bl)) != 0)
  {
    if (i == 1) {
      /* incomplete literal tree */
      huft_free(is, tl);
    }
    return i;                   /* incomplete code set */
  }
  bd = dbits;
  if ((i = huft_build(is, ll + nl, nd, 0, cpdist, cpdext, &td, &bd)) != 0)
  {
    if (i == 1) {
      /* incomplete distance tree */
#ifdef PKZIP_BUG_WORKAROUND
    }
#else
      huft_free(is, td);
    }
    huft_free(is, tl);
    return i;                   /* incomplete code set */
#endif
  }

  /* Save inflate state for this block */
  is->tl = tl;
  is->td = td;
  is->bl = bl;
  is->bd = bd;

  return 0;
}

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
)
{
  struct InflateState *is;

  /* Do some argument checking */
  if ((!putbuffer_ptr) || (!malloc_ptr) || (!free_ptr)) return NULL;

  /* Allocate the InflateState memory area */
  is = (struct InflateState *) (*malloc_ptr)(sizeof(struct InflateState));
  if (!is) return NULL;

  /* Set up the initial values of the inflate state */
  is->runtimetypeid1   = INFLATESTATETYPE;
  is->errorencountered = FALSE;

  is->bb               = 0;
  is->bk               = 0;
  is->bp               = 0;
  is->bs               = 0;

  is->wp               = 0;
  is->wf               = 0;

  is->state            = -1;
  is->lastblock        = FALSE;

  is->AppState         = AppState;

  is->putbuffer_ptr    = putbuffer_ptr;
  is->malloc_ptr       = malloc_ptr;
  is->free_ptr         = free_ptr;

  is->runtimetypeid2   = INFLATESTATETYPE;

  /* Return this state info to the caller */
  return is;
}

/* Call-in routine to put a buffer into inflate decompression */
int InflatePutBuffer(                         /* returns 0 on success       */
  void *InflateState,                         /* opaque ptr from Initialize */
  unsigned char *buffer,                      /* buffer to put              */
  long length                                 /* length of buffer           */
)
{
  struct InflateState *is;

  int beginstate;

  /* Get (and check) the InflateState structure */
  is = (struct InflateState *) InflateState;
  if (!is || (is->runtimetypeid1 != INFLATESTATETYPE)
          || (is->runtimetypeid2 != INFLATESTATETYPE)) return TRUE;
  if (is->errorencountered) return TRUE;

  do
  {
    int size, i;
    

    if ((is->state == -1) && (is->lastblock)) break;

    /* Save the beginning state */
    beginstate = is->state;

    /* Push as much as possible into input buffer */
    size = BUFFERSIZE - is->bs;
    if (size > length) size = (int) length;
    i = is->bp + is->bs;

    while (size-- > 0)
    {
      is->buffer[i++ & BUFFERMASK] = *buffer;
      is->bs++;
      buffer++;
      length--;
    }

    /* Process some more data */
    if (is->state == -1)
    {
      int e;                /* last block flag */
      unsigned t;           /* block type */

      ulg b;                /* bit buffer */
      unsigned k;           /* number of bits in bit buffer */

      /* make local copies of state */
      b = is->bb;           /* initialize bit buffer */
      k = is->bk;           /* initialize bit count */

      TRY
      {
        /* read in last block bit */
        NEEDBITS(1)
        e = (int)b & 1;
        DUMPBITS(1)

        /* read in block type */
        NEEDBITS(2)
        t = (unsigned)b & 3;
        DUMPBITS(2)

        if (t <= 2)
        {
          is->state     = t;
          is->lastblock = e;
        }
        else
        {
          ERROREXIT(is);
        }
      }
      CATCH_BEGIN
      CATCH_END
        
      /* restore the state from the locals */
      is->bb = b;              /* restore bit buffer */
      is->bk = k;              /* restore bit count */
    }
    else if (is->state == 0)
    {
      int ret;

      ret = inflate_stored_setup(is);

      if (ret > 0)
        ERROREXIT(is);

      if (ret == 0) is->state += 10;
    }
    else if (is->state == 1)
    {
      int ret;

      ret = inflate_fixed_setup(is);

      if (ret > 0)
        ERROREXIT(is);

      if (ret == 0) is->state += 10;
    }
    else if (is->state == 2)
    {
      int ret;

      ret = inflate_dynamic_setup(is);
      
      if (ret > 0)
        ERROREXIT(is);

      if (ret == 0) is->state += 10;
    }
    else if (is->state == 10)
    {
      int ret;

      ret = inflate_stored(is);

      if (ret > 0)
        ERROREXIT(is);

      if (ret == 0)
      {
        is->state = -1;
      }
    }
    else if ((is->state == 11) ||
             (is->state == 12)    )
    {
      int ret;

      ret = inflate_codes(is, is->tl, is->td, is->bl, is->bd);

      if (ret > 0)
        ERROREXIT(is);

      if (ret == 0)
      {
        /* free the decoding tables */
        huft_free(is, is->tl);
        huft_free(is, is->td);
        is->state = -1;
      }
    }
    else
    {
      ERROREXIT(is);
    }
  }
  while (length || (is->state != beginstate));
  
  FLUSHWINDOW(is->wp, TRUE);

  return is->errorencountered;
}

/* Routine to terminate inflate decompression */
int InflateTerminate(                         /* returns 0 on success       */
  void *InflateState                          /* opaque ptr from Initialize */
)
{
  int err;
  void (*free_ptr)(void *buffer);

  struct InflateState *is;

  /* Get (and check) the InflateState structure */
  is = (struct InflateState *) InflateState;
  if (!is || (is->runtimetypeid1 != INFLATESTATETYPE)
          || (is->runtimetypeid2 != INFLATESTATETYPE)) return TRUE;

  /* save the error return */
  err = is->errorencountered || (is->bs > 0)
                             || (is->state != -1)
                             || (!is->lastblock);

  /* save the address of the free routine */
  free_ptr = is->free_ptr;

  /* Deallocate everything */
  (*free_ptr)(is);

  return err;
}
