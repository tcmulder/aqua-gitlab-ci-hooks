# aqua-hooks

## Description
The aqua-hooks script enables GitLab, the aquamarine webservers, and local development machines to talk to each other.

## Requirements First
These instructions assume you already have a few things set up.

1. You need to set up a config.php file with values for your server. There's a config-sample.php to get you started: just duplicate, rename, and edit it. Some of the values you can hard code, but some (like the repository URL to pull) you need to set up to be dynamically populated. There are a lot of ways you could do this:
    
    **Command Line Arguments**
    
    Command line arguments are available in the PHP array ``$argv``, so you could pass in arguments individually like ``php aqua-hooks.php 'git@GitLab.com:example.git' 'my-client' 'my-project'`` (and so on) and then in the config file:

    ```    
    'repo' => $argv[1],
    'client' => $argv[2],
    'project' => $argv[3],
    //etc.
    ```

    **Query String**
    
    Especially if you're using the webhook, it can be helpful to set up your variables as query strings like ``https://hooks.example.com/aqua-hooks.php?repo=example&client=my-client&project='my-project`` and then in the config file:

    ```    
    'repo' => 'git@GitLab.com"' . htmlspecialchars($_GET['example']) . '.git',
    'client' => htmlspecialchars($_GET['my-client']),
    'project' => htmlspecialchars($_GET['my-project']),
    //etc.
    ```

    **JSON**
    
    JSON is my preferred method. You can read incoming JSON from a webhook like ``$gitlab = json_decode(file_get_contents('php://input'));`` and then extract what you need from that:
    
    ```    
    'repo' => $gitlab->repository->url;
    'client' => $gitlab->client;
    'project' => $gitlab->project;
    //etc.
    ```


    **Combine Them**
    Initially I used webhooks, so most data came as JSON from GitLab and I added in a couple things it didn't provide through query strings. Now that I'm using GitLab CI, I send everything via the command line, but pass it in as JSON to make it easier for myself. It's up to you!
`

2. Your server will need to have subdomains set up (for example, pushing branch ``dev`` is going to launch the site to ``dev.example.com``, which needs to exist already).
3. You need to have a deployment key set up between GitLab and your server so the server can pull code (essentially private key on your server that connects to a public key on GitLab).
4. If you're using GitLab CI, you need to have a public key on your server and have the private key documented for GitLab so it can execute the script on the server (you'll see where you enter that private key in the instructions below).

## Setup
These are the basic setup instructions to use the script.

After creating a project in GitLab:

1. Enable the deployment key for the repo in Settings > Repository.

Then, if you're using GitLab CI:

1. Go to Settings > CI/CD Pipelines and add your private key as a secret variable called SSH_PRIVATE_KEY.
2. Create a .gitlab-ci.yml file that will SSH into our server and execute the script—something like ``- ssh user@123.45.67.89 "php /var/www/hooks/aqua-hooks.php ${CONFIG}"``. Your config file needs to parse the arguments passed into the script (${CONFIG} in this instance).

Or, if you're using GitLab webhooks:

1. Add the web hook ``http://YOUR_SERVER_ADDRESS/aqua-hooks.php/?client=client_folder_name&project=project_folder_name`` under Settings > Integrations (changing the query string to match any values you must pass in).

Your GitLab project is now connected to your webserver.

## Usage
The aqua-hooks script is pretty flexible. Basically, when pushed up to GitLab, branches similar to the following will get pulled into your server's ``dev.example.com`` subdomain via the aqua-hooks script:

- ``dev``
- ``dev_feature``
- ``dev_slider``

Similarly, you can push to any other aquamarine subdomain by prefixing your branch name appropriately:

- ``test`` will get pulled into ``test.example.com``
- ``test_qa`` will get pulled into ``test.example.com``
- ``stage`` will get pulled into ``stage.example.com``

### Usage for WordPress Sites
The aqua-hooks script can handle the database for WordPress sites. You just need to make sure the configuration is aware that the project ``type`` is equal to ``wordpress``. (If however you choose to handle updating the database manually, there's no need to implement the following instructions: just leave the type blank instead.)

You need to have a database dump you'd like the aqua-hooks script to use in the root of the project named ``/.db/db.sql``. No find and replace is necessary as the script will replace the value of ``home`` url with the appropriate url based on the server that's pulling in the code.

I'd highly recommend adding a pre-commit hook that always adds your database to your repo whenever you commit, something like ``mysqldump -hlocalhost -uroot -proot database_name --skip-dump-date --extended-insert=FALSE > ./.db/db.sql && git add ./.db/db.sql`` in the file ``.git/hooks/pre-commit``. Alternatively, you can just dump the file there yourself whenever you want the database to update.

## Extended Description
The aqua-hooks script is flexible enough to handle a variety of situations.

### Where Files Go
The script will add the code in subdirectories of your subdomain servers. For example, if the path to your subdomain is /var/www/dev.example.com/ and your client is 'client-name' and your project is 'project-name', then the script will add files to /var/www/dev.example.com/client-name/project-name/. This allows you to push multiple client sites or multiple site projects within the same client to the server without them overwriting each other. In this example, your project would be available at ``https://dev.example.com/client-name/project-name/``.

### WordPress Sites
If you tell the script your project is a WordPress site, it employs some special functionality specific to the CMS. It relies on the details you feed it through the ``wp-config.php`` file, so ensure these are as you want them to be before pushing to GitLab.

Then, a couple of things can happen with the database:

- If the database doesn't exist, the script creates it.
- For existing databases, the script will drop tables, import the ``/.db/db.sql`` file, and do a find and replace based on the imported database ``home`` url.

The database will be based on the subdomain, client, and project, like ``dev_client_name_project_name`` for a push of the branch dev for client 'client-name', project 'project-name' (hyphens are changed to underscores). It will always use the root MySQL user.

The script will also update your .htaccess and wp-config.php files to reflect the new paths, URLs, database credentials, etc. for the subdomain it's launching to.

The aqua-hooks script can work with WordPress sites that are using a subdirectory install (i.e. there are .htaccess and index.php files at root, and a subdirectory containing the rest of the WordPress install). It also works with projects not at root, so if on your local machine the site is housed at ``http://localhost/sites/in-development/client-name/project-name/current``, aqua-hooks will attempt to move everything onto the subdomain at ``https://dev.example.com/client-name/project-name`` just like it normally would. Note however that some plugins or other configurations may add paths into files (particularly the .htaccess file) or the database that aqua-hooks doesn't know to update, so be sure to do some testing if your local and server URLs will use different paths like this.

### Logging
You can turn on logging by passing in the ``&log=basic`` query. (Another option is ``&log=debug``, which will include the file name and line number from the aqua-hooks script itself; unless you're working on the aqua-hooks script, this is probably unnecessary.) You can SSH into the server and tail this file for continuous logging like this:

``ssh -t YOUR_USERNAME@YOUR_SERVER_ADDRESS 'tail -f /PATH_FROM_ROOT/aqua-hooks/webhook.log  && bash'``

It will also echo each logged value, so it will show up in GitLab CI's pipeline logs in the browser.

The script uses a pretty primitive logging system but is better than nothing. Some PHP errors will also be logged in this file. The file will get truncated to 1000 lines when it reaches 100000 lines to ensure it doesn't get unmanageable. Therefore, don't wait too long to check the log or your results will get overwritten, and when the truncation occurs on occasion you might need to rerun your tail.

### Server Check
The script checks to ensure the branch matches an available server. If you push a branch named ``dev_feature_name``, it will pull changes into ``dev.example.com``. However, if you push ``blah_feature``, the script will cease execution if you don't have a ``bleh.example.com`` subdomain set up. It's important to note that your GitLab repo is unaware of the aqua-hooks script's activities, so you can certainly push such branches to GitLab and it will track your changes just fine.

This adds quite a bit of flexibility as you can pull changes into any webserver subdomain that follows the ``subdomain.example.com`` format. If you add a server called, say, ``preview.example.com``, the script will automatically pull any pushes to branches named ``preview`` or ``preview_*`` into that subdomain for you.

### Automated Backup Commits
The script backs up the last five working directory states on branches in the format ``gitlab_autosave_at_Y_m_d_H_i_s`` (for example, ``gitlab_autosave_at_2014_11_02_17_14_08`` was saved on November 02, 2014 at 17 hours in 24 hour format, 14 minutes, and 8 seconds; it's essentially biggest to smallest time measurements). The script just keeps the last five automated backup commits, deleting older automated commits as needed.

If you specified the project type as being ``wordpress`` in your config file, the script will also attempt to find a ``wp-config.php`` file and include a database dump in the automated backup commit.

### Existing Projects in Directory
If there is existing code in the project directory, the automated backup commit will preserve it for you, whether it's currently a git repository or not.

### Nonexistent Directories
If the client directory doesn't yet exist, the script creates it for you. If the project directory doesn't exist, the creates it and pulls the code into it.

### Preview Branch
The script always uses a branch called ``gitlab_preview``. Don't actually use this branch in your projects as the history gets really messed up: it's just for previewing purposes.

### Duplicate Pushes
If the current commit for the targeted webserver is identical to the commit the the config is requesting to pull, the aqua-hooks script will exit and won't perform the update. This prevents data loss in the event GitLab encounters a non-fatal error and racks up a queue of retries and repeatedly attempts to overwrite what's on the webserver with the same commit. If you push two branches that share an identical commit, the aqua-hooks script will similarly exit (e.g. git push and get the "already up to date" message).

### Pull a Specific Branch
Sometimes you may want to trigger the pull from GitLab to the webserver without repeatedly pushing from your local machine. This is especially true if your webserver pulled the code in wrong for some reason, but locally and on GitLab the code is fine. You can pass in a different ``pull`` perimeter to your config file, and aqua-hooks will ignore any branch being pushed and _always_ grab that particular branch. Make sure to remove this configuration once you're done or the script will continue to only work with that branch and its corresponding server, regardless of the branch you push.

## Changelog

### 4.0.0
- Major reworking from zen to aqua version.
- Uses better config.php strategy rather than requiring URL query strings.
- Works better with GitLab CI.

### 3.0.2
- Added better error checking for database scripts.

### 3.0.1
- Added ability to pull a specific branch via the ``pull=branch_name`` query string.

### 3.0.0
- Changed paths to work on new server.
