<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Robo\Task\loadTasks;
use Partridge\Utils\Util;

/**
 * Segregates the robo tasks concerned with packaging and building of repo projects
 */
trait RoboFileBuildTrait
{
    use loadTasks;

  /**
   * Dev task to merge Dirty branch into Dev and handle additional things such as versioning numbers
   *  - merges Dirty into Dev
   *  - bumps the semver patch number. Will be of the form MAJOR.MINOR.PATCH
   *  (to bump major/minor, a separate commit should be done beforehand)
   */
    public function buildMergeDev($opts = ['no-composer' => false, 'quick' => false, 'super-quick' => false]) {
        $this->stopOnFail(true);

        $foundBranch = $this->getCurrentGitBranch();
        if ($foundBranch != 'dirty') {
            throw new \Robo\Exception\TaskException(__CLASS__, "You must be on the branch 'dirty'. Branch found: ${foundBranch}");
        }

        $coll = $this->collectionBuilder();
        if (!$opts['no-composer']) {
            $coll->taskComposerUpdate()
            ->rawArg('partridge/utils') // makes sure composer.lock has latest proper utils and tests
    //         ->rawArg('partridge/testing') // does not matter if out of sync
            ->printOutput(true)
            ;
        }
        if (is_callable([$this, 'doBuildMergeDev'])) {
            $coll->addCode(function () use ($opts) {
                $this->doBuildMergeDev($opts);
            });
        }

        if (!$opts['super-quick']) {
            if ($opts['quick']) {
                $coll->addCode(function () {
                    $this->testQuick();
                });
            } else {
                $coll->addCode(function () {
                    $this->testAll();
                });
            }
        }

  //    $result = $this->taskUtilsSemVer('.semver')
  //                   ->increment('patch')
  //                   ->run()
  //    ;
  //    if (!$result->wasSuccessful()) {
  //      throw new \Robo\Exception\TaskException(__CLASS__ee, "Bad semver file");
  //    }

        return $coll
        ->taskUtilsSemVer('.semver')
        ->increment('patch')
        ->taskGitStack()
        ->stopOnFail(true)
        ->add('.semver')
        ->add('.semver.plain')
        ->add('composer.lock')
        ->commit('Bumps .semver')
        ->push()
        ->checkout('dev')
        ->merge('dirty -m="merge branch dirty into dev"')
        ->push()
        ->checkout('dirty')
        ->run()
        ;
    }

  /**
   * Hits up Shippable and triggers a build of the project specified.
   * Abstraction of a couple of things here - as a way to invoke any shippable project but
   * mainly to be used to trigger building of images (release or base images)
   * Ideal for calling after a deployment on GKE or wherever.
   *
   * It is envisioned that individual projects should be added to the conditionals towards the bottom
   * to faciliate easy invocations via just the one parameter
   *
   * ./robo shippable:build all             // all standard images
   * ./robo shippable:build php-apache-api  // specific standard image
   * ./robo shippable:build frontend --release --branch=ceda465f18a7f7a2b0c4119427d592323e8d0f85  // build frontend project at that revision (best for post-shippable hooks)
   * ./robo shippable:build frontend --release                                                              // build frontend project at default revision (tip of "dev" branch)
   * ./robo shippable:build api --release                                                                   // build api project (results in api & importer images)
   *
   * http://docs.shippable.com/platform/api/api-overview/
   */
    public function shippableBuild($imageOrProjectName, $opts = ['release' => false, 'branch' => null, 'dry-run' => false]) {
        $callShippable = function ($projectId, $buildProject, $globalEnvs) use ($opts) {
            $url = "https://api.shippable.com/projects/${projectId}/newBuild";
            $ch = curl_init();
            $this->say("URL: ${url}");

            $shippableApiToken = getenv('SYMFONY__SHIPPABLEAPITOKEN');
            if (!$shippableApiToken) {
                throw new \RuntimeException("Cannot trigger shippable build without token set as env variable at 'SYMFONY__SHIPPABLEAPITOKEN'");
            }

            $headers = [
            "Authorization: apiToken ${shippableApiToken}",
            'Content-Type: application/json',
            ];

            $bodyJson = "{\"globalEnv\": ${globalEnvs}, \"branchName\": \"dirty\"}";   // !! branchName here refers to the branch of the `docker-image` repo and not the intended build branch of the target buildable project
            $this->say("body: ${bodyJson}");

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $bodyJson
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            if (!$opts['dry-run']) {
                $rawRes = @curl_exec($ch);
                if (!($res = json_decode($rawRes, true)) || !isset($res['runId'])) {
                    throw new \RuntimeException('Could not json decode the response or imvalid key used. Raw: '.Util::consolePrint($rawRes));
                }
                $this->say("Shippable build triggered at https://app.shippable.com/github/elmpp/${buildProject}/runs/{$res['runNumber']}/1/console");
            }

            $this->say("body: ${bodyJson}");
            curl_close($ch);
        };

      // The "release" flag means we'll be invoking the docker-images project to create an image featuring our code
        if ($opts['release']) {
            $buildProject = 'docker-images';

          // we can specify a "treeish" (branch) that should be built - http://stackoverflow.com/questions/4044368/what-does-tree-ish-mean-in-git
            $revisionJsonPart = $opts['branch'] ? ', "partridge_treeish": "'.$opts['branch'].'"' : '';

            $projectId = $this->getShippableDetails($buildProject)['id'];
            if ($imageOrProjectName == 'api') {
                $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "api"'.$revisionJsonPart.'}');
                $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "importer"'.$revisionJsonPart.'}');
            } // frontend etc
            else {
                $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "'.$imageOrProjectName.'"'.$revisionJsonPart.'}');
            }
        } // standard shippable project (no globalEnv etc required)
        elseif (in_array($imageOrProjectName, ['testing', 'api', 'frontend'])) {
            $buildProject = $imageOrProjectName;
            $projectId = $this->getShippableDetails($buildProject)['id'];
            $callShippable->__invoke($projectId, $imageOrProjectName, '{}');
        } // OTHER PROJECTS HERE BY IMAGEorProjectNAME??

      // standard project build (can include docker-images which will result in all standard images being built
        else {
            $buildProject = 'docker-images';
            $projectId = $this->getShippableDetails($buildProject)['id'];
    //      $callShippable->__invoke($projectId, $imageOrProjectName, '{"partridge_target": "' . $imageOrProjectName . '"}');
            $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "'.$imageOrProjectName.'"}');
        }
    }

    protected function getShippableDetails($project = 'api') {
        $project = str_replace('-', '_', $project);
        $constant = strtoupper("SHIPPABLE_PROJECT_${project}");

        return constant("self::$constant");
    }
}
