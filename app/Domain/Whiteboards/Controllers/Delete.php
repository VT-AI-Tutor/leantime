<?php

namespace Leantime\Domain\Whiteboards\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Whiteboards\Services\Whiteboards as WhiteboardService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delete - confirms and performs deletion of a whiteboard.
 */
class Delete extends Controller
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
    }

    /**
     * Shows the delete confirmation dialog.
     *
     * @param  array  $params  Request parameters (id of the whiteboard).
     */
    public function get(array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $board = $this->whiteboardService->getBoard($id);

        if ($board === null || (int) $board['projectId'] !== (int) session('currentProject')) {
            return Frontcontroller::redirect(BASE_URL.'/whiteboards/showBoards');
        }

        $this->tpl->assign('board', $board);

        return $this->tpl->display('whiteboards.delete');
    }

    /**
     * Deletes the whiteboard and redirects back to the list.
     *
     * @param  array  $params  Request parameters (id of the whiteboard).
     */
    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return Frontcontroller::redirect(BASE_URL.'/whiteboards/showBoards');
        }

        $id = (int) ($params['id'] ?? ($_POST['id'] ?? 0));
        $this->whiteboardService->deleteBoard($id);

        $this->tpl->setNotification($this->language->__('notification.whiteboard_deleted'), 'success');

        return Frontcontroller::redirect(BASE_URL.'/whiteboards/showBoards');
    }
}
