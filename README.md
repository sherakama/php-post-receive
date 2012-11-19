# IXM PHP-based branch deployment script

The POST callback you would put into GitHub or Bitbucket would be:
> http://<server>/<vhost dir name>

For example if your vhost dir is 'ons.ixm.ca'
> http://git.post-receive.ixm.ca/ons.ixm.ca

The URL can have three variables appended to the end, ak, bn, cc. For example
> http://git.post-receive.ixm.ca/ons.ixm.ca?ak=12345abcde&cc=cssminusjs&bn=develop

ak stands for api-key which is currently set to a static string '12345abcde'

bn stands for branch name, which should usually be 'develop', 'stage', and 'prod'

cc stands for clear cache, which uses drush commands, can take 'all' for 'drush cc all', 'cssplusjs' for drush cc css+js, and 'cssminusjs' for drush cc css-js

This post-receive script will pull only from commits that are tagged with git tags.

It will pull commits from the tag that is the highest version number, using the Drupal versioning standard.

For example,
7.x-1.0 > 7.x-0.9
7.x-1.1 > 7.x-1.0
7.x-1.10 > 7.x-0.2
7.x-2.0 > 7.x-1.99

For branch stage, beta version number will be taken into consideration.

Note this means that stage branch tags will need to include beta1, beta2, etc at the end.

For example,
7.x-1.0beta1 > 7.x-0.9beta5
7.x-1.0beta3 > 7.x-1.0beta2

If a commit is pushed without tags the script will not deploy the untagged commit.

Note that git push --tags is needed to push tags to the remote repository.

If you forget to tag before pushing to the repo, you can tag it then push --tags again.

Note that this script is to be only deployed on Drupal 7.x projects.
