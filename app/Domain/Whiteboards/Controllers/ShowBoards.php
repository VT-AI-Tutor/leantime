<?php

namespace Leantime\Domain\Whiteboards\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Whiteboards\Services\Whiteboards as WhiteboardService;
use Symfony\Component\HttpFoundation\Response;

/**
 * ShowBoards - lists the project whiteboards and handles creation of new ones.
 */
class ShowBoards extends Controller
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
     * Displays the list of whiteboards for the current project.
     *
     * @param  array  $params  Request parameters.
     */
    public function get(array $params): Response
    {
        $this->tpl->assign('whiteboards', $this->whiteboardService->getAllBoards((int) session('currentProject')));
        $this->tpl->assign('canEdit', AuthService::userIsAtLeast(Roles::$editor));

        return $this->tpl->display('whiteboards.showBoards');
    }

    /**
     * Creates a new whiteboard and redirects to its editor.
     *
     * @param  array  $params  Request parameters.
     */
    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return Frontcontroller::redirect(BASE_URL.'/whiteboards/showBoards');
        }

        if (isset($_POST['newBoard'])) {
            $id = $this->whiteboardService->createBoard(
                $_POST['title'] ?? '',
                (int) session('currentProject'),
                (int) session('userdata.id'),
            );

            return Frontcontroller::redirect(BASE_URL.'/whiteboards/show/'.$id);
        }

        return Frontcontroller::redirect(BASE_URL.'/whiteboards/showBoards');
    }
}
