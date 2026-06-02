<?php

namespace Leantime\Domain\Reports\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Reports\Services\Reports as ReportService;
use Leantime\Domain\Sprints\Services\Sprints as SprintService;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class Show extends Controller
{
    private ProjectService $projectService;

    private SprintService $sprintService;

    private TicketService $ticketService;

    private ReportService $reportService;

    /**
     * @throws BindingResolutionException
     */
    public function init(
        ProjectService $projectService,
        SprintService $sprintService,
        TicketService $ticketService,
        ReportService $reportService
    ): void {
        Auth::authOrRedirect([Roles::$owner, Roles::$admin, Roles::$manager, Roles::$editor]);

        $this->projectService = $projectService;
        $this->sprintService = $sprintService;
        $this->ticketService = $ticketService;

        session(['lastPage' => BASE_URL.'/reports/show']);

        $this->reportService = $reportService;
        $this->reportService->dailyIngestion();
    }

    /**
     * @throws BindingResolutionException
     */
    public function get(array $params): Response
    {
        $currentProject = (int) session('currentProject');

        // Project Progress
        $this->tpl->assign('projectProgress', $this->projectService->getProjectProgress($currentProject));
        $this->tpl->assign('currentProjectName', $this->projectService->getProjectName($currentProject));

        // Sprint Burndown
        $requestedSprintId = isset($params['sprint']) ? (int) $params['sprint'] : null;
        $allSprints = $this->sprintService->getAllSprints($currentProject);

        $sprintBurndown = $this->reportService->getSprintBurndownForReport($currentProject, $requestedSprintId);

        $this->tpl->assign('sprintBurndown', $sprintBurndown['chart']);

        if ($allSprints !== false && count($allSprints) > 0) {
            $this->tpl->assign('currentSprint', $sprintBurndown['currentSprintId']);
        }

        $this->tpl->assign('backlogBurndown', $this->sprintService->getCummulativeReport($currentProject));
        $this->tpl->assign('allSprints', $allSprints);

        $this->tpl->assign('fullReport', $this->reportService->getFullReport($currentProject));
        $this->tpl->assign('fullReportLatest', $this->reportService->getRealtimeReport($currentProject, ''));

        $this->tpl->assign('states', $this->ticketService->getStatusLabels());

        // Milestones
        $allProjectMilestones = $this->ticketService->getAllMilestones(['sprint' => '', 'type' => 'milestone', 'currentProject' => $currentProject]);
        $this->tpl->assign('milestones', $allProjectMilestones);

        // Team performance breakdown for a selectable month (admins/owners only).
        // Month boundaries are resolved in the user's timezone, then queried in UTC.
        if (Auth::userIsAtLeast(Roles::$admin)) {
            $timezone = session('usersettings.timezone') ?: 'UTC';

            try {
                // Pin the day to 01 so createFromFormat cannot overflow into the next month.
                $monthBase = isset($params['month'])
                    ? CarbonImmutable::createFromFormat('Y-m-d', ((string) $params['month']).'-01', $timezone)->startOfDay()
                    : CarbonImmutable::now($timezone)->startOfMonth()->startOfDay();
            } catch (\Throwable $e) {
                $monthBase = CarbonImmutable::now($timezone)->startOfMonth()->startOfDay();
            }

            $startUtc = $monthBase->setTimezone('UTC')->format('Y-m-d H:i:s');
            $endUtc = $monthBase->addMonth()->setTimezone('UTC')->format('Y-m-d H:i:s');
            $canGoNext = $monthBase->addMonth()->lte(CarbonImmutable::now($timezone)->startOfMonth());

            $this->tpl->assign('teamPerformance', $this->reportService->getTeamPerformanceReport(
                $currentProject,
                $startUtc,
                $endUtc,
                $timezone,
                (int) $monthBase->daysInMonth
            ));
            $this->tpl->assign('teamMonthLabel', $monthBase->translatedFormat('F Y'));
            $this->tpl->assign('teamPrevMonth', $monthBase->subMonth()->format('Y-m'));
            $this->tpl->assign('teamNextMonth', $canGoNext ? $monthBase->addMonth()->format('Y-m') : null);
        } else {
            $this->tpl->assign('teamPerformance', []);
            $this->tpl->assign('teamMonthLabel', '');
            $this->tpl->assign('teamPrevMonth', null);
            $this->tpl->assign('teamNextMonth', null);
        }

        return $this->tpl->display('reports.show');
    }

    public function post($params): Response
    {
        return Frontcontroller::redirect(BASE_URL.'/dashboard/show');
    }
}
