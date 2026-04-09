<?php

declare(strict_types=1);

namespace App\Repositories;

final class SyncLogRepository extends BaseRepository
{
    public function create(?int $showId, string $provider, string $scope, string $status, ?string $message = null, array $context = [], ?int $durationMs = null): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('sync_logs') . ' (`show_id`, `provider`, `scope`, `status`, `message`, `context`, `duration_ms`, `ran_at`) ' .
            'VALUES (:show_id, :provider, :scope, :status, :message, :context, :duration_ms, NOW())'
        );
        $statement->execute([
            'show_id' => $showId,
            'provider' => $provider,
            'scope' => $scope,
            'status' => $status,
            'message' => $message,
            'context' => $this->encodeJson($context),
            'duration_ms' => $durationMs,
        ]);
    }
}
