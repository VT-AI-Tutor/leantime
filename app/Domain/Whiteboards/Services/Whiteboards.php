<?php

namespace Leantime\Domain\Whiteboards\Services;

use Leantime\Core\Events\DispatchesEvents;
use Leantime\Domain\Whiteboards\Repositories\Whiteboards as WhiteboardRepository;

/**
 * Whiteboards service - business logic for project Excalidraw whiteboards.
 *
 * Whiteboards are shared per project: any project member can view them and
 * editors can create, rename, draw on and delete them. The Excalidraw scene
 * (elements + appState + embedded files) is persisted as a JSON blob so the
 * team can asynchronously brainstorm on the same board.
 */
class Whiteboards
{
    use DispatchesEvents;

    /**
     * @param  WhiteboardRepository  $whiteboardsRepo  Data access layer.
     */
    public function __construct(
        private WhiteboardRepository $whiteboardsRepo,
    ) {}

    /**
     * Returns all whiteboards for a project (without scene payload).
     *
     * @param  int  $projectId  Project id.
     * @return array<int, array<string, mixed>> Whiteboard rows.
     */
    public function getAllBoards(int $projectId): array
    {
        return $this->whiteboardsRepo->getAllBoards($projectId);
    }

    /**
     * Returns a single whiteboard (including scene) by id, or null when not found.
     *
     * @param  int  $id  Whiteboard id.
     * @return array<string, mixed>|null Whiteboard row.
     */
    public function getBoard(int $id): ?array
    {
        return $this->whiteboardsRepo->getBoard($id);
    }

    /**
     * Creates a new (empty) whiteboard for a project.
     *
     * @param  string  $title  Whiteboard title.
     * @param  int  $projectId  Project the whiteboard belongs to.
     * @param  int  $authorId  Author user id.
     * @return int The new whiteboard id.
     */
    public function createBoard(string $title, int $projectId, int $authorId): int
    {
        $title = trim($title) !== '' ? trim($title) : 'Untitled whiteboard';

        $id = $this->whiteboardsRepo->addBoard([
            'title' => $title,
            'projectId' => $projectId,
            'author' => $authorId,
            'scene' => '',
        ]);

        self::dispatchEvent('whiteboardCreated', ['id' => $id, 'projectId' => $projectId]);

        return $id;
    }

    /**
     * Renames a whiteboard.
     *
     * @param  int  $id  Whiteboard id.
     * @param  string  $title  New title.
     * @return bool True when updated.
     *
     * @api
     */
    public function renameBoard(int $id, string $title): bool
    {
        if (! $this->boardIsInCurrentProject($id)) {
            return false;
        }

        return $this->whiteboardsRepo->updateTitle($id, trim($title));
    }

    /**
     * Deletes a whiteboard.
     *
     * @param  int  $id  Whiteboard id.
     * @return bool True when deleted.
     */
    public function deleteBoard(int $id): bool
    {
        if (! $this->boardIsInCurrentProject($id)) {
            return false;
        }

        return $this->whiteboardsRepo->deleteBoard($id);
    }

    /**
     * Guards cross-project access: confirms the whiteboard belongs to the
     * project currently active in the session. The CurrentProject middleware
     * has already verified the user may access that project, so this prevents
     * a user from mutating a board outside their accessible project via the
     * JSON-RPC endpoint.
     *
     * @param  int  $id  Whiteboard id.
     * @return bool True when the board is in the current session project.
     */
    private function boardIsInCurrentProject(int $id): bool
    {
        $board = $this->whiteboardsRepo->getBoard($id);

        return $board !== null && (int) $board['projectId'] === (int) session('currentProject');
    }
}
