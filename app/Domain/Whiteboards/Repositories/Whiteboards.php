<?php

namespace Leantime\Domain\Whiteboards\Repositories;

use Illuminate\Database\ConnectionInterface;
use Leantime\Core\Db\Db as DbCore;

/**
 * Whiteboards repository - data access for the zp_whiteboards table.
 */
class Whiteboards
{
    /** @var ConnectionInterface Database connection */
    private ConnectionInterface $db;

    /**
     * @param  DbCore  $db  Database connection wrapper.
     */
    public function __construct(DbCore $db)
    {
        $this->db = $db->getConnection();
    }

    /**
     * Returns all whiteboards (without scene payload) for a project, newest first.
     *
     * @param  int  $projectId  Project id.
     * @return array<int, array<string, mixed>> Whiteboard rows.
     */
    public function getAllBoards(int $projectId): array
    {
        $rows = $this->db->table('zp_whiteboards')
            ->select(['zp_whiteboards.id', 'zp_whiteboards.title', 'zp_whiteboards.projectId', 'zp_whiteboards.author', 'zp_whiteboards.created', 'zp_whiteboards.modified', 'zp_user.firstname', 'zp_user.lastname', 'zp_user.profileId'])
            ->leftJoin('zp_user', 'zp_whiteboards.author', '=', 'zp_user.id')
            ->where('zp_whiteboards.projectId', $projectId)
            ->orderByDesc('zp_whiteboards.modified')
            ->orderByDesc('zp_whiteboards.id')
            ->get();

        return array_map(fn ($row) => (array) $row, $rows->toArray());
    }

    /**
     * Returns a single whiteboard (including scene) by id.
     *
     * @param  int  $id  Whiteboard id.
     * @return array<string, mixed>|null Whiteboard row or null when not found.
     */
    public function getBoard(int $id): ?array
    {
        $row = $this->db->table('zp_whiteboards')
            ->where('id', $id)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Creates a new whiteboard and returns its id.
     *
     * @param  array<string, mixed>  $values  Keys: title, projectId, author, scene.
     * @return int New whiteboard id.
     */
    public function addBoard(array $values): int
    {
        return (int) $this->db->table('zp_whiteboards')->insertGetId([
            'title' => $values['title'] ?? '',
            'projectId' => $values['projectId'] ?? 0,
            'author' => $values['author'] ?? 0,
            'scene' => $values['scene'] ?? '',
            'created' => now(),
            'modified' => now(),
        ]);
    }

    /**
     * Updates the title of a whiteboard.
     *
     * @param  int  $id  Whiteboard id.
     * @param  string  $title  New title.
     * @return bool True when a row was updated.
     */
    public function updateTitle(int $id, string $title): bool
    {
        return $this->db->table('zp_whiteboards')
            ->where('id', $id)
            ->update([
                'title' => $title,
                'modified' => now(),
            ]) > 0;
    }

    /**
     * Deletes a whiteboard.
     *
     * @param  int  $id  Whiteboard id.
     * @return bool True when a row was deleted.
     */
    public function deleteBoard(int $id): bool
    {
        return $this->db->table('zp_whiteboards')
            ->where('id', $id)
            ->delete() > 0;
    }
}
