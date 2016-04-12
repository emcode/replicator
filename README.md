# replicator
CLI utility for mirroring large numbers of GIT repositories and composer dependencies

Using this project you can create mirrors / clone of all dependencies for given project based on it's composer.json file. This together with satis allows for faster composer installations and development without access to the internet.


# usage

Create mirror repositories of all composer dependencies of existing PHP project:

`php ./replicator.php replicate <project-dir>`

Create single mirror repository from URL:

`php ./replicator.php mirror:create <remote-git-repository>`

You can pass options to controll what wil be the name and path of created mirror:

`php ./replicator.php mirror:create <remote-git-repository> --mirror-name=MIRROR-NAME`

`php ./replicator.php mirror:create <remote-git-repository> --parent-path=PARENT-PATH`

Update previously created mirror repository:

`php ./replicator.php mirror:update <local-git-repository>`

Update multiple repositories inside given path. This finds bare repositores recursively:

`php ./replicator.php mirror:update <some-optional-path> --all`
