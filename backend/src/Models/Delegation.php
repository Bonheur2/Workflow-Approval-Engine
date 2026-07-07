<?php

namespace App\Models;

class Delegation extends BaseModel
{
    protected static string $table = 'delegations';

    public static function create(int $delegatorId, int $delegateId, string $startDate, string $endDate): int
    {
        return static::insertInto('delegations', [
            'delegator_id' => $delegatorId,
            'delegate_id' => $delegateId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'active' => 1,
        ]);
    }

    public static function revoke(int $id): bool
    {
        return static::updateTable('delegations', $id, ['active' => 0]);
    }

    public static function forDelegator(int $delegatorId): array
    {
        $stmt = static::db()->prepare('SELECT * FROM delegations WHERE delegator_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id' => $delegatorId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns the active delegate standing in for $delegatorId on $date
     * (format Y-m-d), or null if no active delegation covers that date.
     */
    public static function activeDelegateFor(int $delegatorId, string $date): ?int
    {
        $stmt = static::db()->prepare(
            'SELECT delegate_id FROM delegations
             WHERE delegator_id = :id AND active = 1 AND start_date <= :date AND end_date >= :date
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['id' => $delegatorId, 'date' => $date]);
        $row = $stmt->fetch();
        return $row ? (int) $row['delegate_id'] : null;
    }
}
