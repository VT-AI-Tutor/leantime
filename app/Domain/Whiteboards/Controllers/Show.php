<?php

namespace Leantime\Domain\Whiteboards\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Whiteboards\Services\Whiteboards as WhiteboardService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Show - renders the Excalidraw editor for a single whiteboard.
 */
class Show extends Controller
{
    private WhiteboardService $whiteboardService;

    /**
     * Initializes dependencies.
     *
     * @param  WhiteboardService  $whiteboardService  Whiteboards business logic.
     */
    public function init(WhiteboardService $whiteboardService): void
    {
        $this->whiteboardService = $whiteboardService;

        session(['lastPage' => CURRENT_URL]);
    }

    /**
     * Displays the whiteboard editor.
     *
     * @param  array  $params  Request parameters (id of the whiteboard).
     */
    public function get(array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $board = $this->whiteboardService->getBoard($id);

        // Guard cross-project access: only show boards in the active project.
        if ($board === null || (int) $board['projectId'] !== (int) session('currentProject')) {
            return Frontcontroller::redirect(BASE_URL.'/whiteboards/showBoards');
        }

        $this->tpl->assign('board', $board);
        $this->tpl->assign('canEdit', AuthService::userIsAtLeast(Roles::$editor));

        return $this->tpl->display('whiteboards.show');
    }
}
