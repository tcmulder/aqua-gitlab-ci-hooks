** UNDER DEVELOPMENT **




# aqua-hooks 4.0.0 Server Setup

## Description
The aqua-hooks script enables gitlab, the aquamarine webservers, and our local machines to talk to each other.

This ``master`` branch contains instructions pertinent to gitlab and the aquamarine webservers.

If you're trying to set up your local machine, check out [local setup instructions](https://github.com/tcmulder/aqua-hooks/tree/local) in the ``local`` branch's ``readme.md`` or [download the local files](https://github.com/tcmulder/aqua-hooks/archive/local.zip).

## Setup
These are the basic setup instructions; you may want to skim over the other script options to see if there are other features you'd like to take advantage of.

After creating a project in gitlab:

1. Add the web hook ``http://YOUR_SERVER_ADDRESS/aqua-hooks/?client=client_folder_name&project=project_folder_name`` under Settings > Web Hooks (replacing client and project folder names appropriately).
2. Enable the ``aquamarine Web Servers`` deployment key in Settings > Deployment Keys.

Your gitlab project is now connected to the aquamarine web servers.

## Usage
The aqua-hooks script is pretty flexible. Most interaction after setup happens locally, and you can [find instructions here](https://github.com/tcmulder/aqua-hooks/tree/local).

Basically, when pushed up to gitlab, branches similar to the following will get pulled into ``YOUR_SERVER_ADDRESS`` via the aqua-hooks script:

- ``dev``
- ``dev_feature``
- ``dev_slider``

Similarly, you can push to any other aquamarine web server by prefixing your branch name appropriately:

- ``test`` will get pulled into ``YOUR_SERVER_ADDRESS``
- ``test_qa`` will get pulled into ``YOUR_SERVER_ADDRESS``
- ``stage`` will get pulled into ``YOUR_SERVER_ADDRESS``

### Usage for WordPress Sites
The aqua-hooks script can handle the database for WordPress sites. (If however you choose to handle updating the database manually, there's no need to implement the following instructions.) You just need to pass in the additional query ``&type=wp`` with the url. For example:

``http://YOUR_SERVER_ADDRESS/aqua-hooks/?client=client_folder_name&project=project_folder_name&type=wp``

You should have a few things in place before pushing up changes for WordPress sites:

1. Your ``aqua-config.php`` should have the correct database credentials for all servers you intend to pull into.

2. Dump a database you'd like the aqua-hooks script to use into ``/.db/db.sql``. No find and replace is necessary as the script will replace the value of ``siteurl`` with the appropriate url based on the server that's pulling in the code.

## Extended Description
The aqua-hooks script is flexible enough to handle a variety of situations.

### WordPress Sites
If you tell the script your project is a WordPress site, it employs some special database handling capabilities. It relies on the details you feed it through the ``aqua-config.php`` file, so ensure these are as you want them to be before pushing to gitlab. Then, a couple of things can happen:

- If the database doesn't exist, the script creates it with a same-named user.
- For existing databases, the script will drop tables, import the ``/.db/db.sql`` file, and do a find and replace based on the imported database ``siteurl``.

### Logging
You can turn on logging by passing in the ``&log=true`` query. (Another option is ``&log=debug``, which will include the file name and line number from the aqua-hooks script itself; unless you're working on the aqua-hooks script, this is probably unnecessary.) You can SSH into the server and tail this file for continuous logging like this:

``ssh -t YOUR_USERNAME@YOUR_SERVER_ADDRESS 'tail -f /PATH_FROM_ROOT/aqua-hooks/webhook.log  && bash'``

The script uses a pretty primitive logging system but is better than nothing. Some PHP errors will also be logged in this file. The file will get truncated to 1000 lines when it reaches 100000 lines to ensure it doesn't get unmanageable. Therefore, don't wait too long to check the log or your results will get overwritten, and when the truncation occurs on occasion you might need to rerun your tail.

### Pull a Specific Branch
Sometimes you may want to trigger the pull from gitlab to the webserver without repeatedly pushing from your local machine. For instance, if you forgot to add ``&debug=true`` or ``&type=wp`` then the code is accurate locally and on gitlab, but has been pulled in improperly to the webserver.

Normally the aqua-hooks script will pull the branch from the push that initiated the webhook call. However, if you create a new webhook and add the query string ``&pull=some_branch``, you can click the Test Hook button in gitlab to pull in that branch instead. This behaves just like pushing that branch, so "testing" ``dev`` will result in the ``YOUR_SERVER_ADDRESS`` webserver pulling in the latest from the ``dev`` branch, ``test_feature`` will pull in the ``test_feature`` branch to the ``YOUR_SERVER_ADDRESS`` server, and so on.

Make sure to immediately remove this webhook once you're done with it or every push to gitlab will continue to pull the same branch regardless of what branch you're pushing up.

### Server Check
The script checks to ensure the branch matches an available server. If you push a branch named ``dev_feature_name``, it will pull changes into ``YOUR_SERVER_ADDRESS``. However, if you push ``blah_feature`` or ``master``, the script will cease execution. It's important to note that gitlab is unaware of the aqua-hooks script's activities, so you can certainly push such brances to gitlab and it will track your changes just fine.

This adds quite a bit of flexibility as you can pull changes into any aquamarine web server that follows the ``aqua_servername1`` format. If we add a server (as we did with ``discovery1.aquamarine.com`` a while ago), the script will automatically be compatible.

### Preview Branch
The script always uses a branch called ``gitlab_preview``. Don't actually use this branch in your projects as the history gets really messed up (the script uses reset --hard to avoid merge conflicts).

### Automated Backup Commits
The script also backs up the last five working directory states on branches in the format ``gitlab_autosave_at_Y_m_d_H_i_s`` (for example, ``gitlab_autosave_at_2014_11_02_17_14_08`` was saved on November 02, 2014 at 17 hours in 24 hour format, 14 minutes, and 8 seconds; it's essentially biggest to smallest time measurements). The script just keeps the last five automated backup commits, deleting older automated commits as needed.

If you specified in the webhook url that your project is a WordPress-type project, the script will also attempt to find a ``aqua-config.php`` file and include a database dump in the automated backup commit.

### Existing Projects in Directory
If there is existing code in the project directory, the automated backup commit will preserve it for you, whether it's currently a git repository or not.

### Nonexistent Directories
If the client directory doesn't yet exist, the script creates it and copies the ``client_template`` setup. If the project directory doesn't exist, the creates it and pulls the code into it. In theory, this makes the script compatible with Tom's server promotion app, although there may be some issues (e.g. currently script doesn't recognize the new aqua-config.php setup).

### Duplicate Pushes
If the current commit for the targeted webserver is identical to the commit the the webhook is requesting to pull, the aqua-hooks script will exit and won't perform the update. This prevents data loss in the event gitlab encounters a non-fatal error and racks up a queue of retries and repeatedly attempts to overwrite what's on the webserver with the same commit. If you push two branches that currently share an identical commit, the aqua-hooks script will similarly exit.

## To Do:
The aqua-hooks script can still use improvement. If you have time to implement any of these (or to make overall improvements to existing scripts), feel free to contribute. Here's the future enhancement list:

- Create local setup script/file set.
- Handle back-to-back pushes (e.g. ensure one finishes before the next can start).
- Create a status log (beyond the log=true feature) we can use to see when the script has finished running.
- Eliminate the ``client_name`` and ``project__name`` webhook url requirement for WordPress sites by checking the ``.htaccess`` paths.
- Eliminate the ``type=wp`` webhook url requirement by checking automatically (e.g. checking for aqua-config.php or wp-content or something).

## Changelog

## 4.0.0
- Major reworking from aqua to aqua version, accepting data from GitLab CI rather than a GitLab webhook.

### 3.0.2
- Added better error checking for database scripts.

### 3.0.1
- Added ability to pull a specific branch via the ``pull=branch_name`` query string.

### 3.0.0
- Changed paths to work on new server.
