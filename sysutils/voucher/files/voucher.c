/*
    Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>.
    All rights reserved.
    
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

/*

   This program creates and verifies vouchers based on public/private key
   and configuration settings.

   Before the program can be used, a public/private RSA key pair must be 
   generated. The maximum key length supported is 64 Bits. Using shorter keys 
   will make the generated vouchers shorter but eventually less secure.

   To generate a valid RSA key pair using 64 Bits, use:

   $ openssl genrsa 64 > key64.private
   $ openssl rsa -pubout < key64.private >key64.public

   Next, a config file is needed, containing one line as follows:

   rollbits,ticketbits,checksumbits,magic,charset

   An example file looks like:
   $ cat voucher.cfg 
   16,10,5,1174491274,1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ

   Content: more info can also be found in the m0n0wall GUI.

   rollbits:     Number of Bits used to store the Roll Id. 
   ticketbits:   Number of Bits used to store the Ticket Id. 
   checksumbits: Number of Bits used to store a checksum over Roll Id and Ticket Id.
   magic:        a unique number to make the generated vouchers unique.
   charset:      The generated vouchers use characters out of this character set. 
                 Avoid e.g. letters/numbers that can be confused like 0 and O
                 Character set is case sensitive.

   To generate and test vouchers using the example settings above, use:

   $ ./voucher -c voucher.cfg -p key64.private 0 5
   # Voucher Tickets 1..5 for Roll 0
   # Nr of Roll Bits     16
   # Nr of Ticket Bits   10
   # Nr of Checksum Bits 5
   # magic initializer   1174491274 (32 Bits used)
   # Character Set used  1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ
   #
   " 6UVeTS6II68"
   " kGZ38iBgyx4"
   " gWUhfyvWo43"
   " kiViNq31p7b"
   " jLzSQuZMPJa"

   Note: the generated vouchers will be different due to the generated RSA key pair, even
   if the same config file is used.

   To test the vouchers, use:

   $ ./voucher -c voucher.cfg -k key64.public 6UVeTS6II68
   OK 0 1
   
   $ ./voucher -c voucher.cfg -k key64.public kGZ38iBgyx4 gWUhfyvWo43 kiViNq31p7b jLzSQuZMPJa
   OK 0 2
   OK 0 3
   OK 0 4
   OK 0 5

   Brief explanation on how the vouchers are created is below. But nothing beats reading
   the code ;-)
   
   Voucher content is stored into a 64Bit integer, then encrypted.
   Rollid, TicketId and Checksum all have configurable bit lengths.

   voucher_bits = rollid_bits + ticketid_bits + checksum_bits
   voucher_bytes = int(voucher_bits / 8) + 1

   63    voucher_bits                               0
   +----//----+------------+------------+-----------+
   |  magic   |  checksum  |  ticketid  |  rollid   |
   +----//----+------+-----+------------+-----------+

   padding: Unfortunately its not long enough to do proper 
   PKCS1 PAdding (which requires 11 characters). Therefore we
   add a magic number to fill it up and verify against.

   checksum is not really a checksum but rather a modulo of 
   (ticketid+rollid) % (2^checksum_bits). It seems weak, because
   ticket 4 from roll 30 will have the same checksum as ticket 30
   from roll 4 but only of the rolls might be allowed on a given
   gateway. 

   charset: can contain any 8 bit ASCII character except space and double
   quote (' ' and '"') and comma.

*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <assert.h>
#include <sys/types.h>
#include <openssl/evp.h>
#include <openssl/pem.h>
#include <openssl/rsa.h>
#ifdef DEBUG
#include <openssl/err.h>
#endif

#ifdef DEBUG
#define HELP "\n\
voucher [-d] [-c cfg_file] -k public_key voucher\n\
voucher [-d] [-c cfg_file] -p private_key roll count\n\
voucher [-d] -s -k private_key\n\
\n"
#else
#define HELP "\n\
voucher [-c cfg_file] -k public_key voucher\n\
voucher [-c cfg_file] -p private_key roll count\n\
voucher -s -k private_key\n\
\n"
#endif

// default config file, unless specified via option '-c'. 
// This file must contain one line with:
// roll_bits,ticket_bits,charset
#define DEFAULT_CFG_FILE "./voucher.cfg"

// Max key size in bytes. Longer keys would
// be more secure, but the resulting vouchers
// would be hard to type in error free by humans
// ... and we can't use u_int64_t numbers anymore ;-)
#define MAX_RSA_KEY_LEN 8

// how many characters we will allow to 
// generate vouchers. If you change this here
// check use places too. Will trigger assert()
#define MAX_CHARSET_LEN 255 

// buffer to hold one voucher string. 
// 64 is enough to hold vouchers with a charset of "01"
#define MAX_VOUCHER_LEN 64

// Error messages returned to caller via stdout
#define MSG_OK      "OK"
#define ERR_TYPO    "TYPO"  // bad code, e.g. too short/long, wrong checksum
#define ERR_CRYPT   "CRYPT" // crypto error. See stderr for detailed msg
#define ERR_VRFY    "VRFY"  // verification error
#define ERR_CFG     "CFG"  // config error

#ifdef DEBUG
int         debug = 0;
#endif

static void usage()
{
    fprintf(stderr,HELP);
    exit(1);
}

/*
 * Convert long long integer value into preallocated buffer
 * in networking order (MSB first)
 */
static void ll2buf(u_int64_t ll, unsigned char *buf, int len)
{
    int i;

#ifdef DEBUG
    if (debug)
        fprintf(stderr, "ll2buf len=%d %.16llx -> ", len, ll);
#endif

    for (i=len-1;i>=0;i--)
    {
        buf[i] = ll & 0xff;
        ll >>= 8;
    }
#ifdef DEBUG
    if (debug) 
    {
        for (i=0;i<len;i++)
            fprintf(stderr, "%.2x ", buf[i]);
        fprintf(stderr, "\n");
    }
#endif
}

/* 
 * Convert buffer into long long integer value
 * in networking order (MSB first)
 */
static void buf2ll(unsigned char *buf,u_int64_t *ll, int len)
{
    int i;

    *ll = buf[0];
    for (i=1;i<len;i++)
    {
        *ll <<= 8;
        *ll += buf[i];
    }

#ifdef DEBUG
    if (debug) 
    {
        fprintf(stderr, "buf2ll len=%d ",len);
        for (i=0;i<len;i++)
            fprintf(stderr, "%.2x ", buf[i]);
        fprintf(stderr, "-> %.16llx\n", *ll);
    }
#endif

}

/*  ================================================================ */
int main(int argc, char *argv[]) {

    enum        {UNDEFINED, PRINT, CHECK, KEYSIZE} action = UNDEFINED;

    char        *keyFile = NULL;
    char        *cfgFile = DEFAULT_CFG_FILE;
    FILE	*fkey, *fcfg;
    EVP_PKEY	*key = NULL;

    unsigned char   clearbuf[MAX_RSA_KEY_LEN];
    unsigned char   cryptbuf[MAX_RSA_KEY_LEN];

    char        codebuf[MAX_VOUCHER_LEN+1];

    u_int64_t   clearcode,cryptcode,magic;
    u_int32_t   rollid = 0, ticketid;
    u_int32_t   checksum;
    u_int32_t   ticketcount = 0;

    int         roll_bits, ticket_bits, checksum_bits, magic_bits;
    int         voucher_len, avail_len, crypt_len;
    int         num;

    char        *p,*q;

    char        charset[MAX_CHARSET_LEN+1];
    int         base;
    int         ch;
    int         report_keysize = 0; 

    while ((ch = getopt(argc, argv, "sdp:k:c:")) != -1)
    {
        switch(ch)
        {
#ifdef DEBUG
            case 'd':     // increase ebug level
                debug++; 
                break;
#endif
            case 's':     // get public key info (keysize)
                report_keysize = 1;
                break;

            case 'k':     // public key => check voucher
                keyFile = optarg;
                action = CHECK;
                break;

            case 'p':     // private key => print voucher(s)
                keyFile = optarg;
                action = PRINT;
                break;

            case 'c':     // config file
                cfgFile = optarg;
                break;
            
		break;

            case '?':
            default:
                usage();

        }
    }
    argc -= optind;
    argv += optind;

    if (PRINT == action && 2 == argc)
    {
        // print vouchers: we need exactly two arguments:
        // roll# and # of vouchers to print
        rollid = atoi(argv[0]);     
        ticketcount = atoi(argv[1]);     
#ifdef DEBUG
        if (debug)
            fprintf(stderr, "PRINT roll=%d count=%d\n", rollid, ticketcount);
#endif
    }
    else if (CHECK == action && argc)
    {
        // check voucher: one or more voucher strings must be present
    }
    else if (report_keysize && keyFile)
    {
        // report key size. All we need is a key
    }
    else
        usage();
    
    /* load key ------------------------------------------------- */
#ifdef DEBUG
    ERR_load_crypto_strings();
#endif
    fkey = fopen(keyFile, "r");
    if (fkey == NULL)
    {
        fprintf(stderr, "can't read key file '%s'\n", keyFile);
        exit(1);
    }

    switch (action)
    {
        case PRINT:
            key = PEM_read_PrivateKey(fkey, NULL, NULL, NULL);
            break;

        case CHECK:
            key = PEM_read_PUBKEY(fkey, NULL, NULL, NULL);
            break;

        default:
            break;
    }
    fclose(fkey);

    // did we successfully read a private or public key?
    if (NULL == key)
    {
        printf(ERR_CRYPT " key error. Wrong public/private?\n");
        exit(1);
    }

    // Currently only keys up to 64 bits are supported. Supporting
    // larger keys requires u_int128_t support or different code
    // to pretty print the encrypted code.
    if ((EVP_PKEY_get_bits(key) / 8) > MAX_RSA_KEY_LEN)
    {
        printf(ERR_CRYPT " RSA Key size (%d) too large. Max %d Bits\n", 
                EVP_PKEY_get_bits(key) * 8,MAX_RSA_KEY_LEN * 8);
        exit(1);
    }

    if (report_keysize)  // just report public/private key size
    {
        printf("%d BITS\n", EVP_PKEY_get_bits(key));
        exit(0);
    }

    /* load cfg file -------------------------------------------- */

    fcfg = fopen(cfgFile, "r");
    if (fcfg == NULL)
    {
        fprintf(stderr, "can't read config file '%s'\n", cfgFile);
        exit(1);
    }
    assert(255 == MAX_CHARSET_LEN); // make sure we're safe with fscanf below
    if (5 != fscanf(fcfg, "%d,%d,%d,%lu,%255s",
                &roll_bits, &ticket_bits, &checksum_bits, &magic, charset))
    {
        printf(ERR_CFG " bad content in cfg file %s\n", cfgFile);
        exit(1);
    }

    if (strlen(charset) < 2)
    {
        printf(ERR_CFG " charset too short '%s'\n", charset);
        exit(1);
    }

    if (roll_bits > 31 || ticket_bits > 31 || checksum_bits > 31)
    {
        printf(ERR_CFG "bits must be between 1..31\n");
        exit(1);
    }

    fclose(fcfg);

    // base is used to compute the conversion from the encrypted
    // number to a string consisting only of characters found in
    // charset. Think dec/hex/oct ... and you'll get it.

    base = strlen(charset);

#ifdef DEBUG
    if (debug)
        fprintf(stderr, "roll_bits=%d ticket_bits=%d checksum_bits=%d base=%d charset=%s\n",
                roll_bits, ticket_bits, checksum_bits, base, charset);
#endif

    // How many bytes do we need to store our voucher content?
    voucher_len = 1 + ((roll_bits + ticket_bits + checksum_bits) >> 3);

    // make sure the configured size for roll and voucher plus checksum
    // is smaller than the the key and that it fits into clearcode/cryptcode
    avail_len = (EVP_PKEY_get_bits(key) / 8) < sizeof(clearcode) ?
        (EVP_PKEY_get_bits(key) / 8) : sizeof(clearcode);

    if (voucher_len > avail_len)
    {
        printf(ERR_CFG " roll+voucher+checksum bits too large for given key\n");
        exit(1);
    }

    crypt_len = EVP_PKEY_get_bits(key) / 8;

    // reduce magic number given down to the number of bits we have left
    magic_bits = avail_len * 8 - (roll_bits + ticket_bits + checksum_bits + 1);
    if (magic_bits > 0) {
        magic &= (1LL << magic_bits) - 1; // only keep the bits we can actually store
    } else {
        magic = 0;  // sorry, all bits are used. No magic to verify
        magic_bits = 0;
    }

    if (CHECK == action) // ==================================================
    {
        while (argc)
        {
            /* Convert alphanumeric code in a number */
#ifdef DEBUG
            if (debug)
                fprintf(stderr, "Test voucher <%s>\n", argv[0]);
#endif

            p = strchr(argv[0],'\0');
            cryptcode = 0;
            while(argv[0] < p)
            {
                p--;

                if (' ' == *p)
                    break;

                cryptcode *= base;
                q = strchr(charset, *p);
                if (NULL == q)
                {
                    printf(ERR_TYPO " illegal character (%c) found in %s\n",*p, argv[0]);
                    exit(1);
                }
                cryptcode += q-charset;
            }

            /* move cryptcode into cryptbuf in network order */
            ll2buf(cryptcode, cryptbuf, crypt_len);

            EVP_PKEY_CTX *pctx = EVP_PKEY_CTX_new(key, NULL);
            if (pctx == NULL)
            {
                printf(ERR_TYPO "Failed to create context\n");
                exit (1);
            }
            if (EVP_PKEY_verify_recover_init(pctx) <= 0)
            {
                printf(ERR_TYPO "Failed to init verify context\n");
                exit(1);
            }
            if (EVP_PKEY_CTX_set_rsa_padding(pctx, RSA_NO_PADDING) <= 0)
            {
                printf("Failed to set padding option\n");
                exit(4);
            }
            size_t outsize = crypt_len;
            if (EVP_PKEY_verify_recover(pctx, clearbuf, &outsize, cryptbuf, crypt_len) <= 0)
            {
                printf(ERR_TYPO " Invalid code <%s>\n", argv[0]);
                exit(1);
            }
            else
            {
                /* move clearbuf into clearcode in network order */
                buf2ll(clearbuf, &clearcode, crypt_len);

                /* extract info's out of decrypted code */
                rollid = clearcode & ((1<< roll_bits)-1);
                ticketid = (clearcode >> roll_bits) & ((1<<ticket_bits)-1);
                checksum = clearcode >> (ticket_bits+roll_bits);
                checksum &= (1<<checksum_bits)-1; // get rid of garbage

                /* verify magic */
                if (magic != 
                        ((clearcode >> (roll_bits+ticket_bits+checksum_bits)))) {
                    printf(ERR_TYPO " Invalid magic <%s>\n", argv[0]);
#ifdef DEBUG
                    if (debug) {
                        fprintf(stderr,"magic should be %llu but is %llu magic_bits=%d\n",
                                magic, clearcode >> (roll_bits+ticket_bits+checksum_bits),
                                magic_bits);
                    }
#endif
                    exit(1);
                }
#ifdef DEBUG
                if (debug)
                {
                    fprintf(stderr,"roll=%d ticket=%d checksum=%d magic=%llu voucher=%s clearcode=%llu\n",
                            rollid, ticketid, checksum, magic, argv[0], clearcode);
                }
#endif

                /* simple checksum test to see if user input was valid */
                if ((ticketid + rollid) % (1L<<checksum_bits) != checksum) 
                {
                    printf(ERR_TYPO " Invalid checksum <%s>\n", argv[0]);
                    exit(1);
                }
                else
                    printf(MSG_OK " %d %d\n", rollid, ticketid);
            }
            argc--;
            argv++;
        }
    }
    else if (PRINT == action) // ============================================
    {
        if ( (ticketcount >= 1LL<<ticket_bits) || (ticketcount < 1) )
        {
            printf("Error: Number of Ticket must be 1..%llu\n", 
                    (1LL<<ticket_bits) -1);
            exit(1);
        }
        if (rollid >= 1LL<<roll_bits)
        {
            printf("Error: Roll# must be 0..%llu\n", (1LL<<roll_bits) -1);
            exit(1);
        }

        /* ------------------------------------------------------*/
        printf("# Voucher Tickets 1..%d for Roll %d\n",ticketcount,rollid);
        printf("# Nr of Roll Bits     %d\n", roll_bits);
        printf("# Nr of Ticket Bits   %d\n", ticket_bits);
        printf("# Nr of Checksum Bits %d\n", checksum_bits);
        printf("# magic initializer   %lu (%d Bits used)\n", magic, magic_bits);
        printf("# Character Set used  %s\n", charset);
        printf("#\n");

        /* create individual tickets. Start with ticket 1 because 
         * Roll 0, Ticket 0 would create code 0000-0000, which 
         * can't be encrypted (would result in 0000-0000)
         */

        for (ticketid=1; ticketid <= ticketcount; ticketid++) {

            clearcode = magic << checksum_bits;
            checksum = (ticketid + rollid) % (1<<checksum_bits);
            clearcode += checksum;
            clearcode <<= ticket_bits;
            clearcode += ticketid;
            clearcode = (clearcode << roll_bits) + rollid;
            // make sure the MSB Bit is cleared. Otherwise encryption
            // will fail with 'data too large for modulus'
            clearcode &= 0xffffffffffffffffLL >> (65 - 8*crypt_len);

            /* move clearcode into clearbuf in network order */
            ll2buf(clearcode, clearbuf, crypt_len);

            /* encrypt code */
            EVP_PKEY_CTX *pctx = EVP_PKEY_CTX_new(key, NULL);
            if (pctx == NULL)
            {
                printf("Failed to allocate context\n");
                exit(4);
            }
            if (EVP_PKEY_sign_init(pctx) <= 0)
            {
                printf("Failed to initialise signing context\n");
                exit(4);
            }
            if (EVP_PKEY_CTX_set_rsa_padding(pctx, RSA_NO_PADDING) <= 0)
            {
                printf("Failed to set padding option\n");
                exit(4);
            }
            size_t outsize = crypt_len;
            if (EVP_PKEY_sign(pctx, cryptbuf, &outsize, clearbuf, crypt_len) <= 0)
            {
                printf("Failed to sign\n");
#ifdef DEBUG
                ERR_print_errors_fp(stderr);
#endif
                exit(4);
            }

            /* move cryptbuf into cryptcode in network order */
            buf2ll(cryptbuf, &cryptcode, crypt_len);

            /* pretty print cryptbuf now using charset given above */
            p = codebuf;
            num = sizeof(codebuf); // make sure we don't overflow
            while(cryptcode && --num)
            {
                *p++ = charset[cryptcode % base];
                cryptcode = cryptcode / base;
            }
            *p = '\0';
            if (cryptcode)
            {
                printf(ERR_CFG " voucher gets too long. Charset too small?\n");
                exit(1);
            }
            // Finally: print the newly created voucher !!
            printf("\" %s\"\n",codebuf);

#ifdef DEBUG
            if (debug)
            {
                fprintf(stderr,"roll=%d ticket=%d checksum=%d voucher=%s clearcode=%llu\n",
                        rollid, ticketid, checksum, codebuf, clearcode);
            }
#endif
        }
    }
    exit (0);
}
