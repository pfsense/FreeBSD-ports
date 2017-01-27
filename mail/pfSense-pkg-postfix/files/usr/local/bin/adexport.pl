#!/usr/local/bin/perl -w
##############################################################################
#
# Script to export a list of all email addresses from Active Directory
# Brian Landers <brian@packetslave.com>
#
# This code is in the public domain.  Your use of this code is at your own
# risk, and no warranty is implied.  The author accepts no liability for any
# damages or risks incurred by its use.
#
##############################################################################
# This script would be most useful for generating an access.db file on a
# sendmail gateway server.  You would run it to generate a list of all
# valid email addresses, then insert those addresses into access.db as
# follows:
#
#    To:bob@example.com        RELAY
#    To:jim@example.com        RELAY
#    To:joe@example.com        RELAY
#
# Then, you'd create a default entry for the domain that rejects all other
# recipients (since if they're not in the list, they're by definition invalid).
#
#    To:example.com            ERROR:"User unknown"
#
# For this to work, you need to have "example.com" in your relay-domains
# file (normally /etc/mail/relay-domains), and you need to enable the
# "blacklist_recipients" FEATURE in your sendmail.mc file.
#
#    FEATURE(`blacklist_recipients')
#
# See also my genaccessdb script at packetslave.com for ideas on how to
# generate the access.db file from this list of addresses
#
##############################################################################
# $Id: adexport,v 1.2 2011/08/20 23:30:52 blanders Exp $

use strict;
$|++;

use Net::LDAP;
use Net::LDAP::Control::Paged;
use Net::LDAP::Constant qw( LDAP_CONTROL_PAGED );

#our ($cn,$passwd,$base);
#($cn,$passwd,$base)=@_ARGV;
#print "$cn \n $passwd \n $base";
#exit;

# ---- Constants ----
our $bind    = $ARGV[2].','.$ARGV[1];  # AD account
our $passwd  = $ARGV[3];                        # AD password
our $base    = $ARGV[1];                        # Start from root
our @servers;
push (@servers,$ARGV[0]);
our $filter  = '(|(objectClass=publicFolder)(&(sAMAccountName=*)(mail=*)))';
# -------------------


# We use this to keep track of addresses we've seen
my %gSeen;

# Connect to the server, try each one until we succeed
my $ldap = undef;
foreach( @servers ) {
  $ldap = Net::LDAP->new( $_ );
  last if $ldap;

  # If we get here, we didn't connect
  die "Unable to connect to any LDAP servers!\n";
}

# Create our paging control.  Exchange has a maximum recordset size of
# 1000 records by default.  We have to use paging to get the full list.

my $page = Net::LDAP::Control::Paged->new( size => 100 );

# Try to bind (login) to the server now that we're connected
my $msg = $ldap->bind( dn       => $bind,
                       password => $passwd
                     );

# If we can't bind, we can't continue
if( $msg->code() ) {
  die( "error while binding:", $msg->error_text(), "\n" );
}

# Build the args for the search
my @args = ( base     => $base,
             scope    => "subtree",
             filter   => $filter,
             attrs    => [ "proxyAddresses" ],
             callback => \&handle_object,
             control  => [ $page ],
           );

# Now run the search in a loop until we run out of results.  This code
# is taken pretty much directly from the example code in the perldoc
# page for Net::LDAP::Control::Paged

my $cookie;
while(1) {
  # Perform search
  my $mesg = $ldap->search( @args );

  # Only continue on LDAP_SUCCESS
  $mesg->code and last;

  # Get cookie from paged control
  my($resp)  = $mesg->control( LDAP_CONTROL_PAGED ) or last;
  $cookie    = $resp->cookie or last;

  # Set cookie in paged control
  $page->cookie($cookie);
}

if( $cookie ) {
  # We had an abnormal exit, so let the server know we do not want any more
  $page->cookie($cookie);
  $page->size(0);
  $ldap->search( @args );
}

# Finally, unbind from the server
$ldap->unbind;

# ------------------------------------------------------------------------
# Callback function that gets called for each record we get from the server
# as we get it.  We look at the type of object and call the appropriate
# handler function
#

sub handle_object {

  my $msg  = shift;       # Net::LDAP::Message object
  my $data = shift;       # May be Net::LDAP::Entry or Net::LDAP::Reference

  # Only process if we actually got data
  return unless $data;

  return handle_entry( $msg, $data )     if $data->isa("Net::LDAP::Entry");
  return handle_reference( $msg, $data ) if $data->isa("Net::LDAP::Reference");

  # If we get here, it was something we're not prepared to handle,
  # so just return silently.

  return;
}

# ------------------------------------------------------------------------
# Handler for a Net::LDAP::Entry object.  This is an actual record.  We
# extract all email addresses from the record and output only the SMTP
# ones we haven't seen before.

sub handle_entry {

  my $msg  = shift;
  my $data = shift;

  # Extract the email addressess, selecting only the SMTP ones, and
  # filter them so that we only get unique addresses

  my @mails = grep { /^smtp:/i && !$gSeen{$_}++ }
                   $data->get_value( "proxyAddresses" );

  # If we found any, strip off the SMTP: identifier and print them out
  if( @mails ) {
    print map { s/^smtp:(.+)$/\L$1\n/i; $_ } @mails;
  }
}

# ------------------------------------------------------------------------
# Handler for a Net::LDAP::Reference object.  This is a 'redirect' to
# another portion of the directory.  We simply extract the references
# from the object and resubmit them to the handle_object function for
# processing.

sub handle_reference {

  my $msg  = shift;
  my $data = shift;

  foreach my $obj( $data->references() ) {

    # Oooh, recursion!  Might be a reference to another reference, after all
    return handle_object( $msg, $obj );
  }
}

