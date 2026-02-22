<?php

namespace Drupal\dmf_distributor_core\Entity;

use DateTime;
use DigitalMarketingFramework\Core\Model\Queue\Error;
use DigitalMarketingFramework\Core\Model\Queue\Job as CoreJob;
use DigitalMarketingFramework\Core\Model\Queue\JobInterface;
use DigitalMarketingFramework\Core\Queue\QueueInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\Utility\QueueUtility;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Repository for distributor job queue management.
 *
 * This repository converts between Drupal Job entities (for storage) and
 * core Job objects (for business logic and Twig templates). The Drupal entity
 * is used internally for database operations, while the core Job is returned
 * for all fetch operations to ensure compatibility with Anyrel's templates.
 */
class JobRepository implements QueueInterface
{
    /**
     * Constructs a JobRepository object.
     *
     * @param EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager
     * @param Connection $database
     *   The database connection
     */
    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected Connection $database,
    ) {
    }

    /**
     * Gets the entity storage.
     */
    protected function getStorage(): EntityStorageInterface
    {
        return $this->entityTypeManager->getStorage('dmf_distributor_job');
    }

    /**
     * Converts a Drupal Job entity to a core Job object.
     *
     * @param Job $entity
     *   The Drupal Job entity
     *
     * @return CoreJob
     *   The core Job object
     */
    protected function entityToJob(Job $entity): CoreJob
    {
        $job = new CoreJob(
            environment: $entity->getEnvironment(),
            created: $entity->getCreated(),
            changed: $entity->getChanged(),
            status: $entity->getStatus(),
            skipped: $entity->getSkipped(),
            statusMessage: $entity->getStatusMessage(),
            serializedData: $entity->getSerializedData(),
            hash: $entity->getHash(),
            label: $entity->getLabel(),
            type: $entity->getType(),
            retryAmount: $entity->getRetryAmount(),
        );
        $job->setId($entity->id());

        return $job;
    }

    /**
     * Updates a Drupal Job entity from a core Job object.
     *
     * @param Job $entity
     *   The Drupal Job entity to update
     * @param JobInterface $job
     *   The core Job object with updated values
     */
    protected function updateEntityFromJob(Job $entity, JobInterface $job): void
    {
        $entity->setEnvironment($job->getEnvironment());
        $entity->setCreated($job->getCreated());
        $entity->setChanged($job->getChanged());
        $entity->setStatus($job->getStatus());
        $entity->setSkipped($job->getSkipped());
        $entity->setStatusMessage($job->getStatusMessage());
        $entity->setSerializedData($job->getSerializedData());
        $entity->setHash($job->getHash());
        $entity->setLabel($job->getLabel());
        $entity->setType($job->getType());
        $entity->setRetryAmount($job->getRetryAmount());
    }

    /**
     * Converts an array of Drupal Job entities to core Job objects.
     *
     * @param array<EntityInterface> $entities
     *   Array of Drupal Job entities
     *
     * @return array<CoreJob>
     *   Array of core Job objects
     */
    protected function entitiesToJobs(array $entities): array
    {
        /** @var array<Job> $entities */
        return array_map($this->entityToJob(...), $entities);
    }

    /**
     * {@inheritdoc}
     */
    public function save(JobInterface $item): void
    {
        if ($item instanceof Job) {
            // Already a Drupal entity (e.g., from EntityForm)
            $item->save();
        } else {
            // Core Job - need to load/create entity and update
            $id = $item->getId();
            if ($id !== null) {
                $entity = $this->getStorage()->load($id);
            }

            if (!isset($entity)) {
                $entity = $this->getStorage()->create();
            }

            /** @var Job $entity */
            $this->updateEntityFromJob($entity, $item);
            $entity->save();
            // Update ID on core job if newly created
            if ($item->getId() === null) {
                $item->setId($entity->id());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(int|string $id): ?JobInterface
    {
        $entity = $this->getStorage()->load($id);

        return $entity instanceof Job ? $this->entityToJob($entity) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(JobInterface $item): void
    {
        if ($item instanceof Job) {
            $item->delete();
        } else {
            // Core Job - need to load entity to delete
            $id = $item->getId();
            if ($id !== null) {
                $entity = $this->getStorage()->load($id);
                if ($entity !== null) {
                    $entity->delete();
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByStatus(array $status = [], int $limit = 0, int $offset = 0): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false)
          ->sort('created', 'ASC');

        if ($status !== []) {
            $query->condition('status', $status, 'IN');
        }

        if ($limit > 0) {
            $query->range($offset, $limit);
        }

        $ids = $query->execute();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public function fetchQueued(int $limit = 0, int $offset = 0): array
    {
        return $this->fetchByStatus([QueueInterface::STATUS_QUEUED], $limit, $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchPending(int $limit = 0, int $offset = 0, int $minTimeSinceChangedInSeconds = 0): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false)
          ->condition('status', QueueInterface::STATUS_PENDING)
          ->sort('created', 'ASC');

        if ($minTimeSinceChangedInSeconds > 0) {
            $timestamp = time() - $minTimeSinceChangedInSeconds;
            $query->condition('changed', $timestamp, '<=');
        }

        if ($limit > 0) {
            $query->range($offset, $limit);
        }

        $ids = $query->execute();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public function fetchRunning(int $limit = 0, int $offset = 0, int $minTimeSinceChangedInSeconds = 0): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false)
          ->condition('status', QueueInterface::STATUS_RUNNING)
          ->sort('created', 'ASC');

        if ($minTimeSinceChangedInSeconds > 0) {
            $timestamp = time() - $minTimeSinceChangedInSeconds;
            $query->condition('changed', $timestamp, '<=');
        }

        if ($limit > 0) {
            $query->range($offset, $limit);
        }

        $ids = $query->execute();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public function fetchPendingAndRunning(int $limit = 0, int $offset = 0, int $minTimeSinceChangedInSeconds = 0): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false)
          ->condition('status', [QueueInterface::STATUS_PENDING, QueueInterface::STATUS_RUNNING], 'IN')
          ->sort('created', 'ASC');

        if ($minTimeSinceChangedInSeconds > 0) {
            $timestamp = time() - $minTimeSinceChangedInSeconds;
            $query->condition('changed', $timestamp, '<=');
        }

        if ($limit > 0) {
            $query->range($offset, $limit);
        }

        $ids = $query->execute();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public function fetchDone(int $limit = 0, int $offset = 0): array
    {
        return $this->fetchByStatus([QueueInterface::STATUS_DONE], $limit, $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFailed(int $limit = 0, int $offset = 0): array
    {
        return $this->fetchByStatus([QueueInterface::STATUS_FAILED], $limit, $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsQueued(JobInterface $job): void
    {
        $job->setStatus(QueueInterface::STATUS_QUEUED);
        $job->setChanged(new DateTime());
        $this->save($job);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsPending(JobInterface $job): void
    {
        $job->setStatus(QueueInterface::STATUS_PENDING);
        $job->setChanged(new DateTime());
        $this->save($job);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsRunning(JobInterface $job): void
    {
        $job->setStatus(QueueInterface::STATUS_RUNNING);
        $job->setChanged(new DateTime());
        $this->save($job);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsDone(JobInterface $job, bool $skipped = false): void
    {
        $job->setStatus(QueueInterface::STATUS_DONE);
        $job->setSkipped($skipped);
        $job->setChanged(new DateTime());
        $this->save($job);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsFailed(JobInterface $job, string $message = '', bool $preserveTimestamp = false): void
    {
        $job->setStatus(QueueInterface::STATUS_FAILED);
        if ($message !== '') {
            $job->addStatusMessage($message);
        }

        if (!$preserveTimestamp) {
            $job->setChanged(new DateTime());
        }

        $this->save($job);
    }

    /**
     * {@inheritdoc}
     */
    public function markListAsQueued(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->markAsQueued($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markListAsPending(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->markAsPending($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markListAsRunning(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->markAsRunning($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markListAsDone(array $jobs, bool $skipped = false): void
    {
        foreach ($jobs as $job) {
            $this->markAsDone($job, $skipped);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markListAsFailed(array $jobs, string $message = '', bool $preserveTimestamp = false): void
    {
        foreach ($jobs as $job) {
            $this->markAsFailed($job, $message, $preserveTimestamp);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeOldJobs(int $minAgeInSeconds, array $status = []): void
    {
        $storage = $this->getStorage();
        $timestamp = time() - $minAgeInSeconds;

        $query = $storage->getQuery()
          ->accessCheck(false)
          ->condition('changed', $timestamp, '<=');

        if ($status !== []) {
            $query->condition('status', $status, 'IN');
        }

        $ids = $query->execute();

        if ($ids !== []) {
            $entities = $storage->loadMultiple($ids);
            $storage->delete($entities);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(array $filters): array
    {
        $result = [
            'hashes' => 0,
            'all' => 0,
            'queued' => 0,
            'pending' => 0,
            'running' => 0,
            'done' => 0,
            'doneNotSkipped' => 0,
            'doneSkipped' => 0,
            'failed' => 0,
            'groupedByType' => [],
        ];

        $table = 'dmf_distributor_job';

        // Count distinct hashes
        $query = $this->database->select($table, 'j');
        $query->addExpression('COUNT(DISTINCT hash)', 'count');
        $this->applyTimeframeFiltersToDbQuery($query, $filters);
        $hashCount = $query->execute()->fetchField();
        $result['hashes'] = (int)$hashCount;

        // Group by type, status, skipped and count
        $query = $this->database->select($table, 'j');
        $query->addExpression('COUNT(*)', 'count');
        $query->fields('j', ['type', 'status', 'skipped']);
        $query->groupBy('j.type');
        $query->groupBy('j.status');
        $query->groupBy('j.skipped');
        $this->applyTimeframeFiltersToDbQuery($query, $filters);

        $rows = $query->execute()->fetchAll();

        foreach ($rows as $row) {
            $count = (int)$row->count;
            $type = $row->type;
            $status = (int)$row->status;
            $skipped = (bool)$row->skipped;

            if (!isset($result['groupedByType'][$type])) {
                $result['groupedByType'][$type] = [
                    'all' => 0,
                    'queued' => 0,
                    'pending' => 0,
                    'running' => 0,
                    'done' => 0,
                    'doneNotSkipped' => 0,
                    'doneSkipped' => 0,
                    'failed' => 0,
                ];
            }

            $result['all'] += $count;
            $result['groupedByType'][$type]['all'] += $count;

            switch ($status) {
                case QueueInterface::STATUS_QUEUED:
                    $result['queued'] += $count;
                    $result['groupedByType'][$type]['queued'] += $count;
                    break;

                case QueueInterface::STATUS_PENDING:
                    $result['pending'] += $count;
                    $result['groupedByType'][$type]['pending'] += $count;
                    break;

                case QueueInterface::STATUS_RUNNING:
                    $result['running'] += $count;
                    $result['groupedByType'][$type]['running'] += $count;
                    break;

                case QueueInterface::STATUS_DONE:
                    $result['done'] += $count;
                    $result['groupedByType'][$type]['done'] += $count;

                    $group = $skipped ? 'doneSkipped' : 'doneNotSkipped';
                    $result[$group] += $count;
                    $result['groupedByType'][$type][$group] += $count;
                    break;

                case QueueInterface::STATUS_FAILED:
                    $result['failed'] += $count;
                    $result['groupedByType'][$type]['failed'] += $count;
                    break;
            }
        }

        return $result;
    }

    /**
     * Applies timeframe filters to a database query.
     *
     * @param SelectInterface $query
     *   The database query
     * @param array<string,mixed> $filters
     *   The filters array
     */
    protected function applyTimeframeFiltersToDbQuery(SelectInterface $query, array $filters): void
    {
        if (($filters['minCreated'] ?? null) instanceof DateTime) {
            $query->condition('j.created', $filters['minCreated']->getTimestamp(), '>=');
        }

        if (($filters['maxCreated'] ?? null) instanceof DateTime) {
            $query->condition('j.created', $filters['maxCreated']->getTimestamp(), '<=');
        }

        if (($filters['minChanged'] ?? null) instanceof DateTime) {
            $query->condition('j.changed', $filters['minChanged']->getTimestamp(), '>=');
        }

        if (($filters['maxChanged'] ?? null) instanceof DateTime) {
            $query->condition('j.changed', $filters['maxChanged']->getTimestamp(), '<=');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param array{minCreated:?DateTime,maxCreated:?DateTime,minChanged:?DateTime,maxChanged:?DateTime} $filters
     * @param array{page:int,itemsPerPage:int,sorting:array<string,string>} $navigation
     *
     * @return array<Error>
     */
    public function getErrorMessages(array $filters, array $navigation): array
    {
        // Fetch all failed jobs (applying timeframe filters)
        $failedJobs = $this->fetchFiltered(
            array_merge($filters, ['status' => [QueueInterface::STATUS_FAILED]])
        );

        // Use core utility to aggregate errors by message
        $result = QueueUtility::getErrorStatistics($failedJobs, true);

        // Apply sorting from navigation
        QueueUtility::applyNavigationToErrorStatistics($result, $navigation);

        // Apply pagination
        if ($navigation['itemsPerPage'] > 0) {
            $limit = $navigation['itemsPerPage'];
            $offset = $navigation['itemsPerPage'] * $navigation['page'];
            $result = array_slice($result, $offset, $limit);
        }

        // Convert to Error objects
        return array_map(Error::fromDataRecord(...), $result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchJobTypes(): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false);

        $ids = $query->execute();
        $entities = $storage->loadMultiple($ids);

        $types = [];
        /** @var Job $entity */
        foreach ($entities as $entity) {
            $type = $entity->getType();
            if ($type !== '' && !in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function create(?array $data = null): Job
    {
        /** @var Job */
        return $this->getStorage()->create($data ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function add($item): void
    {
        $this->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($item): void
    {
        $this->delete($item);
    }

    /**
     * {@inheritdoc}
     */
    public function update($item): void
    {
        $this->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchById(int|string $id)
    {
        return $this->fetch($id);
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false)
          ->count();

        return $query->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?array $navigation = null): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false);

        $this->applyNavigation($query, $navigation);

        $ids = $query->execute();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public function countFiltered(array $filters): int
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false)
          ->count();

        $this->applyFilters($query, $filters);

        return $query->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFiltered(array $filters, ?array $navigation = null): array
    {
        $storage = $this->getStorage();
        $query = $storage->getQuery()
          ->accessCheck(false);

        $this->applyFilters($query, $filters);
        $this->applyNavigation($query, $navigation);

        $ids = $query->execute();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOneFiltered(array $filters, ?array $navigation = null)
    {
        $results = $this->fetchFiltered($filters, $navigation !== null ? array_merge($navigation, ['limit' => 1]) : ['limit' => 1]);

        return $results[0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByIdList(array $ids): array
    {
        $storage = $this->getStorage();

        return $this->entitiesToJobs($storage->loadMultiple($ids));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSchema(): ContainerSchema
    {
        // @todo Implement schema if needed for UI
        return new ContainerSchema();
    }

    /**
     * Apply filters to a query.
     *
     * @param mixed $query
     *   The entity query
     * @param array<string,mixed> $filters
     *   The filters to apply
     */
    protected function applyFilters($query, array $filters): void
    {
        // Handle search filter
        if (isset($filters['search']) && $filters['search'] !== '') {
            $this->applySearchFilter($query, $filters);
        }

        // Handle timeframe filters
        if (($filters['minCreated'] ?? null) instanceof DateTime) {
            $query->condition('created', $filters['minCreated']->getTimestamp(), '>=');
        }

        if (($filters['maxCreated'] ?? null) instanceof DateTime) {
            $query->condition('created', $filters['maxCreated']->getTimestamp(), '<=');
        }

        if (($filters['minChanged'] ?? null) instanceof DateTime) {
            $query->condition('changed', $filters['minChanged']->getTimestamp(), '>=');
        }

        if (($filters['maxChanged'] ?? null) instanceof DateTime) {
            $query->condition('changed', $filters['maxChanged']->getTimestamp(), '<=');
        }

        // Handle type filter (array of types)
        if (isset($filters['type']) && $filters['type'] !== []) {
            $query->condition('type', $filters['type'], 'IN');
        }

        // Handle status filter (array of statuses)
        if (isset($filters['status']) && $filters['status'] !== []) {
            $query->condition('status', $filters['status'], 'IN');
        }

        // Handle skipped filter
        if (isset($filters['skipped'])) {
            $query->condition('skipped', (bool)$filters['skipped'] ? 1 : 0);
        }
    }

    /**
     * Apply search filter to a query.
     *
     * @param mixed $query
     *   The entity query
     * @param array<string,mixed> $filters
     *   The filters containing search parameters
     */
    protected function applySearchFilter($query, array $filters): void
    {
        $search = $filters['search'];
        $advancedSearch = (bool)($filters['advancedSearch'] ?? false);
        $searchFields = $filters['searchFields'] ?? ['label', 'type', 'hash', 'status_message'];

        if ($advancedSearch) {
            $searchFields[] = 'serialized_data';
        }

        // Create OR group for search across multiple fields
        $orGroup = $query->orConditionGroup();

        foreach ($searchFields as $field) {
            if ($field === 'status_message') {
                // Status message only searched for failed jobs
                $andGroup = $query->andConditionGroup();
                $andGroup->condition('status', QueueInterface::STATUS_FAILED);
                $andGroup->condition($field, '%' . $search . '%', 'LIKE');
                $orGroup->condition($andGroup);
            } else {
                $orGroup->condition($field, '%' . $search . '%', 'LIKE');
            }
        }

        $query->condition($orGroup);
    }

    /**
     * Apply pagination to a query.
     *
     * @param mixed $query
     *   The entity query
     * @param array<string,mixed>|null $navigation
     *   The navigation parameters
     */
    protected function applyPagination($query, ?array $navigation): void
    {
        if ($navigation === null) {
            return;
        }

        if (isset($navigation['limit']) || isset($navigation['offset'])) {
            $limit = $navigation['limit'] ?? 0;
            $offset = $navigation['offset'] ?? 0;
        } else {
            $limit = $navigation['itemsPerPage'] ?? 0;
            $offset = $limit * ($navigation['page'] ?? 0);
        }

        if ($limit > 0) {
            $query->range($offset, $limit);
        }
    }

    /**
     * Apply sorting to a query.
     *
     * @param mixed $query
     *   The entity query
     * @param array<string,mixed>|null $navigation
     *   The navigation parameters
     */
    protected function applySorting($query, ?array $navigation): void
    {
        if ($navigation !== null && isset($navigation['sorting']) && $navigation['sorting'] !== []) {
            foreach ($navigation['sorting'] as $field => $direction) {
                $query->sort($field, $direction);
            }
        }
    }

    /**
     * Apply navigation (pagination/sorting) to a query.
     *
     * @param mixed $query
     *   The entity query
     * @param array<string,mixed>|null $navigation
     *   The navigation parameters
     */
    protected function applyNavigation($query, ?array $navigation): void
    {
        $this->applyPagination($query, $navigation);
        $this->applySorting($query, $navigation);
    }
}
