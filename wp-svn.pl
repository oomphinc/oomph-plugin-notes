#!/usr/bin/perl

# Usage: perl wp-svn.pl <svn-repo-root>
#
# Run this script to merge the master branch from this repository into the
# subversion repository.
#
# Customize this script by setting $plugin_path and $plugin_file
#
# Excludes paths in /plugin.exclude
use strict;

# The relative path from the Git root to the plugin path
my $plugin_path = '/';

# The relative path from ${gitroot}/${pluginpath} to the plugin main file
my $plugin_file = 'oomph-plugin-notes.php';

sub usage {
	die <<"XXX";
Usage: $0 /path/to/svn/repo

Use this script to perform the correct SVN operations to release the current
master branch (in this git root) to the WordPress plugins repository. This
script will check that the stable tag is correct, does not exist, and is a
higher number than anything existing.

This script operates only on the master git branch. You must make sure that any
changes that are to be merged into the Subversion WordPress repository have
been merged into the git repository.
XXX
}

my $svnroot = shift @ARGV;

&usage() unless $svnroot;

if(!-d $svnroot) {
	die "No such directory: $svnroot";
}

my $branch = `git branch | grep '^*' | cut -d ' ' -f 2`;

chomp $branch;

if($branch ne 'master') {
	die "This script only operates on the 'master' branch";
}

# Get the git root
my $gitroot = `git rev-parse --git-dir | xargs dirname`; chomp $gitroot;

# Ensure $plugin_path begins and ends with / for cleanliness & godliness
$plugin_path =~ s#^/*#/#;
$plugin_path =~ s#/*$#/#;

# Figure out the "stable tag" marked in each version
my $gittag = `grep "Stable tag: " "${gitroot}${plugin_path}readme.txt" | cut -d : -f 2`;

# If this is the first run, assume 0.0.0
my $svntag = -f "$svnroot/trunk/readme.txt" ? `grep "Stable tag: " "$svnroot/trunk/readme.txt" | cut -d : -f 2` : '0.0.0';

# Trim version tags
$gittag =~ s/^\s+|\s+$//g;
$svntag =~ s/^\s+|\s+$//g;

print "Git Version: $gittag; SVN Version: $svntag\n";

# Validate tags
my @gitparts = split /\./, $gittag;
my @svnparts = split /\./, $svntag;

if(@gitparts != 3 || @svnparts != 3) {
	die "Version numbers must each be 3 decimal parts.";
}

# Ensure git version is newer
for(my $i = 0; $i < 3; $i++) {
	next if $gitparts[$i] == $svnparts[$i];
	last if $gitparts[$i] > $svnparts[$i];

	die "Git version must be higher than subversion version.\n";
}

# Ensure the "Version" in the plugin file matches what's in the readme
my $version = `grep "Version: " "$gitroot${plugin_path}${plugin_file}" | cut -d : -f 2`;

$version =~ s/^\s+|\s+$//g;

if($version != $gittag) {
	die "Version in plugin file, $version, does not match stable tag in readme file: $gittag";
}

my $args = '-Lvr --exclude=".*" --exclude=plugin.exclude --exclude=wp-svn.pl --delete';

if(-f "$gitroot/plugin.exclude") {
	$args .= " --exclude-from \"$gitroot/plugin.exclude\"";
}

# Ensure at least trunk exists
system("mkdir -p \"$svnroot/trunk\"");

# Copy the plugin files from the git repository to the subversion repository
system("rsync $args \"$gitroot${plugin_path}\" \"$svnroot/trunk/\"");

# Copy the asset files
if(-d "$gitroot/wp-assets") {
	system("mkdir -p \"$svnroot/assets\"");
	system("rsync $args \"$gitroot/wp-assets/\" \"$svnroot/assets/\"");
}

# Commit svn in trunk
chdir $svnroot;

system("svn add trunk/*");

if(-d "assets") {
	system("svn add assets/*");
}

system("svn commit");

if($?) {
	die "SVN commit was not successful. Giving up";
}

# Copy to stable tag
system("svn copy ^/trunk ^/tags/$gittag");

if($?) {
	die "SVN copy failed. Giving up";
}

print "Dun!\n";
