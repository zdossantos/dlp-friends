<?php

// Coolify is external to this repository. Exercise the emitted PHP against its
// model/helper boundary, recording writes and rejecting unexpected operations.

namespace Illuminate\Support\Facades {
    class DB
    {
        public static function transaction($callback)
        {
            return $callback();
        }
    }
}

namespace App\Models {
    class Application
    {
        public $id = 123;

        public $environment;

        public $git_repository;

        public $git_commit_sha;

        public $git_branch;

        public static function where($field, $value)
        {
            if ($field !== 'uuid' || $value !== 'beta-fixed') {
                throw new \RuntimeException('Unexpected target');
            }

            return new self;
        }

        public function lockForUpdate()
        {
            return $this;
        }

        public function firstOrFail()
        {
            $this->environment = (object) ['name' => getenv('FIXTURE_ENVIRONMENT') ?: 'beta'];
            $this->git_repository = getenv('FIXTURE_REPOSITORY') ?: 'zdossantos/dlp-friends';

            return $this;
        }

        public function environment_variables()
        {
            return $this;
        }

        public function updateOrCreate($identity, $values)
        {
            \recordBetaWrite(['env' => $identity, 'values' => $values]);
        }

        public function save()
        {
            \recordBetaWrite(['sha' => $this->git_commit_sha, 'branch' => $this->git_branch]);
        }
    }

    class ApplicationDeploymentQueue
    {
        public static function where($field, $value = null)
        {
            if (is_array($field)) {
                return new self;
            }
            if ($field !== 'application_id' || $value !== 123) {
                throw new \RuntimeException('Unexpected queue');
            }

            return new self;
        }

        public function whereIn($field, $values)
        {
            if ($field !== 'status' || ! in_array('queued', $values) || ! in_array('in_progress', $values)) {
                throw new \RuntimeException('Missing active state');
            }

            return $this;
        }

        public function exists()
        {
            return ($GLOBALS['fixture_queued'] ?? false) || getenv('FIXTURE_ACTIVE') === 'yes';
        }
    }
}

namespace {
    function recordBetaWrite($data)
    {
        file_put_contents(getenv('WRITE_LOG'), json_encode($data)."\n", FILE_APPEND);
    }

    function new_public_id()
    {
        return 'deployment-fixed';
    }

    function queue_application_deployment($application, $deployment_uuid, $force_rebuild)
    {
        if ($application->id !== 123 || $force_rebuild !== false) {
            throw new RuntimeException('Unexpected deployment');
        }
        if (getenv('FIXTURE_QUEUE_FULL') === 'yes') {
            return ['status' => 'queue_full'];
        }
        $GLOBALS['fixture_queued'] = true;
        recordBetaWrite(['deployment_uuid' => $deployment_uuid]);
    }

    eval(substr($argv[1], strlen('--execute=')));
}
