<?php
/*------------------------------------*\
    ::Aqua Hooks - Update Repository
    ----------------------------------
    author:     Tomas Mulder <dev@thinkaquamarine.com>
    repo:       https://github.com/tcmulder/aqua-gitlab-ci-hooks
    version:    4.0.0
\*------------------------------------*/

// exit if visited in browser or no arguments passed
if(!isset($argv)) exit;

log_status("\n\n:: update_repo included");
// ensure this is a git project
if(file_exists($dir_proj . '.git')){
    log_status('git repo found in '.$dir_proj);
    // run git commands in working directory
    $git = "git --git-dir=$dir_proj.git --work-tree=$dir_proj";
    // get the current status
    $status = log_exec("$git status");

    // create a dated branch name to use for automated commit save
    $prefixed_branch = 'gitlab_autosave_at_'.date(date('Y_m_d_H_i_s'));
    // report the dated branch name
    log_status('automated branch name is '.$prefixed_branch);
    // create the dated branch name and check it out
    log_exec("$git branch $prefixed_branch");
    log_exec("$git checkout $prefixed_branch");
    // for wordpress sites
    if($proj_type == 'wp'){
        log_status('is type wordpress');
        // include the database scripts
        include_once 'lib/functions/db.php';
        // dump the database for the automated commit
        db_export($wp_db_creds, $dir_proj . '.db/');
    }
    // add anything not staged for commit
    log_exec("$git add --all .");
    // create an automated commit on the dated branch
    log_exec("$git commit -m 'Automate commit to save working directory'");
    // report the task that was just requested
    log_status('requested automated commit');

    // get current branches to facilitate old automated commit cleanup
    exec("$git branch", $branches_array);
    log_status('branches array is ' . print_r($branches_array,1));
    // collect the auto saved branches
    $branches_autosaved = Array();
    foreach ($branches_array as $branch_name){
        if (strpos($branch_name,'gitlab_autosave_at') !== false) {
            $branches_autosaved[] = $branch_name;
        }
    }
    // delete any auto saves more than 5
    if(count($branches_autosaved) > 5){
        $branches_to_delete = array_slice($branches_autosaved, 0, (count($branches_autosaved) - 5));
        log_status('deleting all but last 5 automated saves');
        foreach ($branches_to_delete as $branch_name){
            log_exec("$git branch -D $branch_name");
        }
        // get current branches
        $branches_array = Array();
        exec("$git branch", $branches_array);
        log_status('branches array is now ' . print_r($branches_array,1));
    } else {
        log_status('too few automated commits to delete any');
    }

    // fetch the new changes
    log_status('fetch new commit script running');
    // create the gitlab_preview branch (doesn't to do so if it exists already)
    log_exec("$git branch gitlab_preview");
    // checkout the gitlab_preview branch (even if it is already)
    log_exec("$git checkout gitlab_preview");
    // get rid of untracked files and directories
    log_exec("$git clean -f -d");
    // fetch the branch
    log_exec("$git fetch -f --depth=1 gitlab $branch:refs/remotes/gitlab/$branch");
    // reset hard to the branch: no need to preserve history in the gitlab_preview
    log_exec("$git reset --hard gitlab/$branch");
    log_status('reset hard requested on gitlab_preview branch');

// if the .git directory can't be found in the project
} else {
    // talk about it
    throw new Exception($dir_proj . '.git could not be found. Is the deployment key added?');
}
